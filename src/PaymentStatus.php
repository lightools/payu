<?php

namespace Lightools\PayU;

use DateTime;
use InvalidArgumentException;

/**
 * @author Jan Nedbal
 */
class PaymentStatus {

    const STATUS_NEW = 1;
    const STATUS_CANCELED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_STARTED = 4;
    const STATUS_AWAITING = 5;
    const STATUS_RETURNED = 7;
    const STATUS_PAID = 99;
    const STATUS_FAILURE = 888;

    /**
     * @var int[]
     */
    private $states = [
        self::STATUS_NEW,
        self::STATUS_CANCELED,
        self::STATUS_REJECTED,
        self::STATUS_STARTED,
        self::STATUS_AWAITING,
        self::STATUS_RETURNED,
        self::STATUS_PAID,
        self::STATUS_FAILURE,
    ];

    /**
     * @var string
     */
    private $orderId;

    /**
     * @var int
     */
    private $status;

    /**
     * @var DateTime
     */
    private $created;

    /**
     * @var null|DateTime
     */
    private $initialized;

    /**
     * @var null|DateTime
     */
    private $sent;

    /**
     * @var null|DateTime
     */
    private $received;

    /**
     * @var null|DateTime
     */
    private $canceled;

    /**
     * @param string $orderId
     * @param int $status PaymentStatus::STATUS_*
     * @param DateTime $created
     * @param null|DateTime $initialized
     * @param null|DateTime $sent
     * @param null|DateTime $received
     * @param null|DateTime $canceled
     */
    public function __construct($orderId,
                                $status,
                                DateTime $created,
                                DateTime $initialized = NULL,
                                DateTime $sent = NULL,
                                DateTime $received = NULL,
                                DateTime $canceled = NULL) {

        if (!in_array($status, $this->states, TRUE)) {
            throw new InvalidArgumentException("Invalid status $status. Only class constants STATUS_* may be used!");
        }

        $this->orderId = $orderId;
        $this->status = $status;
        $this->created = $created;
        $this->initialized = $initialized;
        $this->sent = $sent;
        $this->received = $received;
        $this->canceled = $canceled;
    }

    /**
     * @return string
     */
    public function getOrderId() {
        return $this->orderId;
    }

    /**
     * One of PaymentStatus:STATUS_*
     * @return int
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     * @return DateTime
     */
    public function getCreated() {
        return $this->created;
    }

    /**
     * @return null|DateTime
     */
    public function getInitialized() {
        return $this->initialized;
    }

    /**
     * @return null|DateTime
     */
    public function getSent() {
        return $this->sent;
    }

    /**
     * @return null|DateTime
     */
    public function getReceived() {
        return $this->received;
    }

    /**
     * @return null|DateTime
     */
    public function getCanceled() {
        return $this->canceled;
    }

}
