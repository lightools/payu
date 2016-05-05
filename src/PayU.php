<?php

namespace Lightools\PayU;

use Bitbang\Http\BadResponseException;
use Bitbang\Http\IClient;
use Bitbang\Http\Request;
use Bitbang\Http\Response;
use DateTime;
use Lightools\Xml\XmlException;
use Lightools\Xml\XmlLoader;

/**
 * @author Jan Nedbal
 */
class PayU {

    const PAYU_URL = 'https://secure.payu.com/paygw/UTF';

    const CHANNEL_CS = 'cs';
    const CHANNEL_MBANK = 'mp';
    const CHANNEL_KB = 'kb';
    const CHANNEL_RF = 'rf';
    const CHANNEL_GE = 'pg';
    const CHANNEL_SBERBANK = 'pv';
    const CHANNEL_FIO = 'pf';
    const CHANNEL_ERA = 'era';
    const CHANNEL_CSOB = 'cb';
    const CHANNEL_CARD = 'c';
    const CHANNEL_TEST = 't';

    /**
     * Channel id => channel name
     * @var string[]
     */
    public static $channels = [
        self::CHANNEL_CS => 'PLATBA 24 - Česká spořitelna',
        self::CHANNEL_MBANK => 'mPeníze - mBank',
        self::CHANNEL_KB => 'MojePlatba - Komerční banka',
        self::CHANNEL_RF => 'ePlatba - Raiffeisenbank',
        self::CHANNEL_GE => 'GE Money Bank',
        self::CHANNEL_SBERBANK => 'Sberbank',
        self::CHANNEL_FIO => 'Fio banka',
        self::CHANNEL_ERA => 'Era/Poštovní spořitelna',
        self::CHANNEL_CSOB => 'ČSOB a.s.',
        self::CHANNEL_CARD => 'Platba kartou online',
        self::CHANNEL_TEST => 'Testovací platba',
    ];

    /**
     * @var int
     */
    private $posId;

    /**
     * @var string
     */
    private $posAuthKey;

    /**
     * @var string
     */
    private $key1;

    /**
     * @var string
     */
    private $key2;

    /**
     * @var string
     */
    private $language = 'cs';

    /**
     * @var IClient
     */
    private $httpClient;

    /**
     * @var XmlLoader
     */
    private $xmlLoader;

    /**
     * @param int $posId
     * @param string $posAuthKey
     * @param string $key1
     * @param string $key2
     * @param IClient $httpClient
     * @param XmlLoader $xmlLoader
     */
    public function __construct($posId, $posAuthKey, $key1, $key2, IClient $httpClient, XmlLoader $xmlLoader) {
        $this->posId = (int) $posId;
        $this->posAuthKey = $posAuthKey;
        $this->key1 = $key1;
        $this->key2 = $key2;
        $this->httpClient = $httpClient;
        $this->xmlLoader = $xmlLoader;
    }

    /**
     * @param string $language en or cs
     */
    public function setLanguage($language) {
        $this->language = $language;
    }

    /**
     * Get URL for new payment to redirect to
     *
     * @param NewPayment $payment
     * @param string $ipAddress
     * @return string
     */
    public function getRedirectUrl(NewPayment $payment, $ipAddress) {
        $signature = md5(
            $this->posId .
            $payment->getPayType() .
            $payment->getSessionId() .
            $this->posAuthKey .
            $payment->getAmount() .
            $payment->getDescription() .
            $payment->getOrderId() .
            $payment->getFirstName() .
            $payment->getSurname() .
            $payment->getEmail() .
            $this->language .
            $ipAddress .
            $payment->getTimestamp() .
            $this->key1
        );

        $query = http_build_query([
            'pos_id' => $this->posId,
            'pos_auth_key' => $this->posAuthKey,
            'client_ip' => $ipAddress,
            'language' => $this->language,
            'amount' => $payment->getAmount(),
            'pay_type' => $payment->getPayType(),
            'session_id' => $payment->getSessionId(),
            'order_id' => $payment->getOrderId(),
            'desc' => $payment->getDescription(),
            'email' => $payment->getEmail(),
            'first_name' => $payment->getFirstname(),
            'last_name' => $payment->getSurname(),
            'ts' => $payment->getTimestamp(),
            'sig' => $signature,
        ]);

        return self::PAYU_URL . '/NewPayment?' . $query;
    }

    /**
     * This is expected to be called when push notification from PayU is received on URL they call UrlOnline
     *
     * @param array $post POST data in HTTP request from PayU
     * @return PaymentStatus
     * @throws InvalidRequestException
     * @throws InvalidSignatureException
     * @throws RequestFailedException
     */
    public function getPaymentStatusChange(array $post) {

        $this->checkReceivedPostData($post);
        $request = $this->buildStatusRequest($post);

        try {
            $response = $this->httpClient->process($request);

            if ($response->getCode() !== Response::S200_OK) {
                throw new RequestFailedException('Unexpected HTTP code from PayU');
            }

            $xmlData = $response->getBody();
            $xmlDom = $this->xmlLoader->loadXml($xmlData);
            $xml = simplexml_import_dom($xmlDom);

            if ((string) $xml->status !== 'OK') {
                throw new RequestFailedException('Unexpected response from PayU');
            }

            $xmlSignature = md5(
                $this->posId .
                $xml->trans->session_id .
                $xml->trans->order_id .
                $xml->trans->status .
                $xml->trans->amount .
                $xml->trans->desc .
                $xml->trans->ts .
                $this->key2
            );
            if ($xmlSignature !== (string) $xml->trans->sig) {
                throw new InvalidSignatureException('Signature in XML from PayU is corrupted');
            }

            return new PaymentStatus(
                (string) $xml->trans->order_id,
                (int) $xml->trans->status,
                (string) $xml->trans->create ? new DateTime($xml->trans->create) : NULL,
                (string) $xml->trans->init ? new DateTime($xml->trans->init) : NULL,
                (string) $xml->trans->sent ? new DateTime($xml->trans->sent) : NULL,
                (string) $xml->trans->recv ? new DateTime($xml->trans->recv) : NULL,
                (string) $xml->trans->cancel ? new DateTime($xml->trans->cancel) : NULL
            );

        } catch (BadResponseException $ex) {
            throw new RequestFailedException('HTTP Request to PayU failed', NULL, $ex);

        } catch (XmlException $ex) {
            throw new RequestFailedException('Invalid XML recieved from PayU', NULL, $ex);
        }
    }

    /**
     * @param array $post
     * @throws InvalidRequestException
     * @throws InvalidSignatureException
     */
    private function checkReceivedPostData(array $post) {

        $requiredFields = ['pos_id', 'session_id', 'ts', 'sig'];
        foreach ($requiredFields as $field) {
            if (!isset($post[$field])) {
                throw new InvalidRequestException("Missing field $field in POST data from PayU");
            }
        }

        $signature = md5($post['pos_id'] . $post['session_id'] . $post['ts'] . $this->key2);
        if ($signature !== $post['sig']) {
            throw new InvalidSignatureException('Signature in POST data from PayU is corrupted');
        }

        if ($this->posId !== (int) $post['pos_id']) {
            throw new InvalidRequestException('Unknown pod_id in POST data from PayU');
        }
    }

    /**
     * @param array $post
     * @return Request
     */
    private function buildStatusRequest(array $post) {
        $data = [
            'pos_id' => $this->posId,
            'session_id' => $post['session_id'],
            'ts' => $post['ts'],
            'sig' => md5($this->posId . $post['session_id'] . $post['ts'] . $this->key1),
        ];
        $headers = ['content-type' => 'application/x-www-form-urlencoded'];
        return new Request(Request::POST, self::PAYU_URL . '/Payment/get', $headers, http_build_query($data));
    }

}
