<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Checkout;

use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureV2;

class WebCheckoutV2
{
    protected string $checkoutVersion = 'v2.1';

    public function __construct(protected readonly SadadConfig $config)
    {
    }

    /**
     * Build a CheckoutResult for the SADAD v2 web checkout flow.
     *
     * Required keys in $orderData:
     *   - order_id      : string   Unique merchant order ID.
     *   - amount        : float    Total transaction amount.
     *   - mobile        : string   Customer mobile number (digits kept only).
     *   - email         : string   Customer email address.
     *   - items         : array[]  Each element: ['order_id', 'amount', 'quantity'].
     *
     * Optional:
     *   - callback_url  : string   Overrides config callbackUrl for this order.
     *
     * @param  array<string, mixed> $orderData
     * @return CheckoutResult
     */
    public function createCheckout(array $orderData): CheckoutResult
    {
        $items       = $orderData['items'] ?? [];
        $callbackUrl = $orderData['callback_url'] ?? $this->config->callbackUrl ?? '';

        // 1. Build core params
        $params = [
            'merchant_id'                    => $this->config->merchantId,
            'ORDER_ID'                       => (string) $orderData['order_id'],
            'WEBSITE'                        => $this->config->website,
            'TXN_AMOUNT'                     => number_format((float) $orderData['amount'], 2, '.', ''),
            'CALLBACK_URL'                   => $callbackUrl,
            'MOBILE_NO'                      => preg_replace('/\D/', '', (string) ($orderData['mobile'] ?? '')),
            'EMAIL'                          => (string) ($orderData['email'] ?? ''),
            'txnDate'                        => date('Y-m-d H:i:s'),
            'SADAD_WEBCHECKOUT_PAGE_LANGUAGE' => strtoupper($this->config->language),
        ];

        // 2. Add VERSION for multi-product (more than 1 item)
        if (count($items) > 1) {
            $params['VERSION'] = '1.1';
        }

        // 3. Generate checksum via SignatureV2 (AES encrypted)
        $params['checksumhash'] = SignatureV2::generate($params, $this->config->secretKey, $this->config->merchantId);

        // 4. Build productdetail array
        $productDetail = [];
        foreach ($items as $item) {
            $productDetail[] = [
                'order_id' => (string) $item['order_id'],
                'amount'   => number_format((float) $item['amount'], 2, '.', ''),
                'quantity' => (string) $item['quantity'],
            ];
        }

        if (!empty($productDetail)) {
            $params['productdetail'] = $productDetail;
        }

        return new CheckoutResult(
            url: $this->config->getCheckoutUrl($this->checkoutVersion),
            params: $params,
        );
    }
}
