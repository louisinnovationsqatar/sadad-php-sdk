<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Webhook;

class WebhookResult
{
    /**
     * @param bool        $isSuccess         True when transactionStatus === 3 (success).
     * @param string      $message           Response message from the gateway.
     * @param string      $transactionNumber Gateway-assigned transaction number.
     * @param string      $orderNumber       Merchant order ID.
     * @param float       $amount            Transaction amount.
     * @param string      $merchantId        Merchant ID from the payload.
     * @param bool        $isTestMode        True when the payload indicates test mode.
     * @param string|null $invoiceNumber     Optional invoice number; null when not present.
     */
    public function __construct(
        public readonly bool    $isSuccess,
        public readonly string  $message,
        public readonly string  $transactionNumber,
        public readonly string  $orderNumber,
        public readonly float   $amount,
        public readonly string  $merchantId,
        public readonly bool    $isTestMode,
        public readonly ?string $invoiceNumber,
    ) {
    }
}
