<?php

namespace Lightools\Tests;

use Bitbang\Http\IClient;
use Bitbang\Http\Response;
use Lightools\PayU\InvalidRequestException;
use Lightools\PayU\InvalidSignatureException;
use Lightools\PayU\NewPayment;
use Lightools\PayU\PaymentStatus;
use Lightools\PayU\PayU;
use Lightools\Xml\XmlLoader;
use Mockery;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

require __DIR__ . '/../vendor/autoload.php';

Environment::setup();

/**
 * @testCase
 * @author Jan Nedbal
 */
class PayUTest extends TestCase {

    protected function setUp() {
        parent::setUp();
        date_default_timezone_set('Europe/Prague');
        Mockery::getConfiguration()->allowMockingNonExistentMethods(FALSE);
    }

    public function testRedirect() {
        $posId = mt_rand(1, 9999);
        $posAuthKey = uniqid();
        $key1 = uniqid();
        $key2 = uniqid();
        $orderId = (string) mt_rand(1, 999);
        $amount = mt_rand(1, 99);
        $language = 'en';
        $payType = PayU::CHANNEL_TEST;
        $description = 'description';

        $httpClient = Mockery::mock(IClient::class);
        $xmlLoader = Mockery::mock(XmlLoader::class);
        $payu = new PayU($posId, $posAuthKey, $key1, $key2, $httpClient, $xmlLoader);
        $payu->setLanguage($language);

        $payment = new NewPayment($orderId, $amount, $payType, $description, 'firstname', 'surname', 'email@example.com');
        $ipAddress = '::1';
        $redirectUrl = $payu->getRedirectUrl($payment, $ipAddress);

        $query = [];
        $urlParts = parse_url($redirectUrl);
        parse_str($urlParts['query'], $query);

        Assert::same('https', $urlParts['scheme']);
        Assert::same($posId, (int) $query['pos_id']);
        Assert::same($posAuthKey, $query['pos_auth_key']);
        Assert::same($ipAddress, $query['client_ip']);
        Assert::same($language, $query['language']);
        Assert::same($amount * 100, (int) $query['amount']);
        Assert::same($payType, $query['pay_type']);
        Assert::same($orderId, $query['order_id']);
        Assert::same($description, $query['desc']);
        Assert::truthy($query['session_id']);
        Assert::truthy($query['sig']);
    }

    public function testPaymentStatus() {
        $posId = '200000';
        $posAuthKey = uniqid();
        $key1 = uniqid();
        $key2 = 'key2';

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getCode')->andReturn(Response::S200_OK);
        $response->shouldReceive('getBody')->andReturn(file_get_contents(__DIR__ . '/responses/payment-status.xml'));

        $httpClient = Mockery::mock(IClient::class);
        $httpClient->shouldReceive('process')->andReturn($response);

        $post = [];
        $post['pos_id'] = $posId;
        $post['session_id'] = uniqid();
        $post['ts'] = date('Y-m-d-H-i-s');
        $post['sig'] = md5($post['pos_id'] . $post['session_id'] . $post['ts'] . $key2);

        $payu = new PayU($posId, $posAuthKey, $key1, $key2, $httpClient, new XmlLoader());
        $status = $payu->getPaymentStatusChange($post);

        Assert::same(PaymentStatus::STATUS_PAID, $status->getStatus());
        Assert::same('2016-05-06 09:07:31', $status->getCreated()->format('Y-m-d H:i:s'));
        Assert::same('2016-05-06 09:07:48', $status->getInitialized()->format('Y-m-d H:i:s'));
        Assert::null($status->getCanceled());
    }

    public function testInvalidRequest() {
        $posId = mt_rand(1, 9999);
        $posAuthKey = uniqid();
        $key1 = uniqid();
        $key2 = uniqid();

        $post1 = [];
        $post1['pos_id'] = $posId;
        $post1['session_id'] = uniqid();
        $post1['ts'] = uniqid();
        $post1['sig'] = 'invalid';

        $post2 = [];
        $post2['pos_id'] = mt_rand(1, 9999); // invalid
        $post2['session_id'] = uniqid();
        $post2['ts'] = uniqid();
        $post2['sig'] = md5($post2['pos_id'] . $post2['session_id'] . $post2['ts'] . $key2);

        $httpClient = Mockery::mock(IClient::class);
        $xmlLoader = Mockery::mock(XmlLoader::class);

        $payu = new PayU($posId, $posAuthKey, $key1, $key2, $httpClient, $xmlLoader);

        Assert::exception(function () use ($payu, $post1) {
            $payu->getPaymentStatusChange($post1);
        }, InvalidSignatureException::class);

        Assert::exception(function () use ($payu, $post2) {
            $payu->getPaymentStatusChange($post2);
        }, InvalidRequestException::class);
    }

    protected function tearDown() {
        parent::tearDown();
        Mockery::close();
    }

}

(new PayUTest)->run();
