<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Callback;

class CallbackResult
{
    /**
     * @param bool   $isSuccess         True when RESPCODE is '1' or 1.
     * @param string $orderNumber       Merchant order ID (ORDERID field).
     * @param string $transactionNumber Gateway transaction number.
     * @param float  $amount            Transaction amount (TXNAMOUNT).
     * @param string $responseCode      SADAD response code (RESPCODE).
     * @param string $responseMessage   Human-readable response message (RESPMSG).
     * @param string $status            Transaction status string (STATUS).
     */
    public function __construct(
        public readonly bool   $isSuccess,
        public readonly string $orderNumber,
        public readonly string $transactionNumber,
        public readonly float  $amount,
        public readonly string $responseCode,
        public readonly string $responseMessage,
        public readonly string $status,
    ) {
    }
}
