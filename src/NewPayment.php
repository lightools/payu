<?php

namespace Lightools\PayU;

use InvalidArgumentException;

/**
 * @author Jan Nedbal
 */
class NewPayment {

    /**
     * @var string
     */
    private $timestamp;

    /**
     * @var string
     */
    private $orderId;

    /**
     * @var string
     */
    private $sessionId;

    /**
     * In hellers
     * @var int
     */
    private $amount;

    /**
     * @var string
     */
    private $payType;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $firstname;

    /**
     * @var string
     */
    private $surname;

    /**
     * @var string
     */
    private $email;

    /**
     * @param string $orderId
     * @param float $amount In CZK
     * @param string $payType PayU::CHANNEL_*
     * @param string $description
     * @param string $firstname
     * @param string $surname
     * @param string $email
     */
    public function __construct($orderId, $amount, $payType, $description, $firstname, $surname, $email) {

        if (!isset(PayU::$channels[$payType])) {
            throw new InvalidArgumentException("Invalid payment type $payType, please use PayU::CHANNEL_*");
        }

        $this->timestamp = date('Y-m-d-H-i-s');
        $this->orderId = $orderId;
        $this->sessionId = $orderId . '_' . $this->timestamp . '_' . mt_rand(100, 999);
        $this->amount = $amount * 100;
        $this->payType = $payType;
        $this->description = $description;
        $this->firstname = $firstname;
        $this->surname = $surname;
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getTimestamp() {
        return $this->timestamp;
    }

    /**
     * @return string
     */
    public function getOrderId() {
        return $this->orderId;
    }

    /**
     * @return string
     */
    public function getSessionId() {
        return $this->sessionId;
    }

    /**
     * Amount in hellers
     * @return int
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getPayType() {
        return $this->payType;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getFirstname() {
        return $this->firstname;
    }

    /**
     * @return string
     */
    public function getSurname() {
        return $this->surname;
    }

    /**
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }

}
