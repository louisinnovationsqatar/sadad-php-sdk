# SADAD Payment Gateway SDK for PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP: 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

Official PHP SDK for the [SADAD Payment Gateway](https://www.sadad.qa/) — Qatar's leading payment platform.

## Features

- [x] Three checkout modes: Web Redirect (v1.1), Enhanced Redirect (v2.1), Embedded/iFrame (v2.2)
- [x] Invoice management: create, share via SMS or email, list
- [x] Full refunds with eligibility validation
- [x] Webhook handling with signature verification
- [x] Payment callback handling (v1.1, v2.1, v2.2)
- [x] SHA-256 and AES-128-CBC signature generation and verification
- [x] Transaction lookup

## Requirements

- PHP 8.1 or higher
- `ext-openssl` extension enabled
- `ext-json` extension enabled
- [Guzzle HTTP Client](https://docs.guzzlephp.org/) ^7.0

## Installation

```bash
composer require louis-innovations/sadad-php-sdk
```

## Quick Start

```php
<?php

use LouisInnovations\Sadad\SadadClient;
use LouisInnovations\Sadad\SadadConfig;

$config = new SadadConfig(
    merchantId:  'your-merchant-id',   // 7-digit SADAD merchant ID
    secretKey:   'your-secret-key',
    website:     'www.your-domain.com',
    environment: 'test',               // 'test' or 'live'
    language:    'eng',                // 'eng' or 'arb'
    callbackUrl: 'https://www.your-domain.com/payment/callback',
    webhookUrl:  'https://www.your-domain.com/payment/webhook',
);

$client = new SadadClient($config);

// Create a checkout
$result = $client->checkout([
    'order_id' => 'ORD-001',
    'amount'   => 150.00,
    'mobile'   => '97412345678',
    'email'    => 'customer@example.com',
    'items'    => [
        ['order_id' => 'ORD-001', 'amount' => 150.00, 'quantity' => 1],
    ],
]);

// Redirect to SADAD or render the form
echo $result->toHtmlForm();
```

## Configuration

All configuration is passed to `SadadConfig`. All parameters except the last four are required.

| Parameter      | Type        | Required | Description                                              |
|----------------|-------------|----------|----------------------------------------------------------|
| `merchantId`   | `string`    | Yes      | Your 7-digit SADAD merchant ID                           |
| `secretKey`    | `string`    | Yes      | Your SADAD secret key                                    |
| `website`      | `string`    | Yes      | Your website domain (e.g. `www.your-domain.com`)         |
| `environment`  | `string`    | No       | `'test'` (default) or `'live'`                           |
| `language`     | `string`    | No       | `'eng'` (default) or `'arb'`                             |
| `callbackUrl`  | `string\|null` | No    | URL SADAD redirects the customer to after payment        |
| `webhookUrl`   | `string\|null` | No    | URL SADAD posts payment notifications to                 |

## Checkout Modes

### v1.1 — Standard Web Redirect

The customer is redirected to the SADAD payment page. A SHA-256 signature is generated from the order parameters.

```php
$result = $client->checkout($orderData, 'v1.1');
header('Location: ' . $result->url . '?' . http_build_query($result->params));
```

### v2.1 — Enhanced Web Redirect

Same redirect flow as v1.1 but uses an AES-128-CBC encrypted checksum for improved security.

```php
$result = $client->checkout($orderData, 'v2.1');
echo $result->toHtmlForm(); // Auto-submitting HTML form
```

### v2.2 — Embedded / iFrame Checkout

Renders an embedded payment widget on your page. Uses the same AES-128-CBC checksum as v2.1 but posts to a separate secure endpoint.

```php
$result = $client->checkout($orderData, 'v2.2');
echo $result->toHtmlForm('sadad-frame', false); // Form only, no auto-submit
```

### Order data structure

```php
$orderData = [
    'order_id'     => 'ORD-001',          // Your unique order identifier
    'amount'       => 150.00,             // Total amount in QAR
    'mobile'       => '97412345678',      // Customer mobile (digits only)
    'email'        => 'customer@example.com',
    'callback_url' => 'https://...',      // Optional: overrides config callbackUrl
    'items'        => [
        [
            'order_id' => 'ORD-001',
            'amount'   => 150.00,
            'quantity' => 1,
        ],
    ],
];
```

## Webhook Setup

1. Log in to [panel.sadad.qa](https://panel.sadad.qa) and register your webhook URL.
2. In your webhook endpoint, pass the raw POST data to the handler:

```php
$payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$result  = $client->handleWebhook($payload);

if ($result->isSuccess) {
    // Payment confirmed — fulfil the order
    // $result->transactionNumber, $result->orderNumber, $result->amount
}

// Acknowledge receipt to SADAD
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(SadadClient::webhookSuccessResponse());
```

`WebhookResult` properties:

| Property            | Type          | Description                                       |
|---------------------|---------------|---------------------------------------------------|
| `isSuccess`         | `bool`        | `true` when `transactionStatus === 3`             |
| `transactionNumber` | `string`      | SADAD transaction reference                       |
| `orderNumber`       | `string`      | Your original order ID                            |
| `amount`            | `float`       | Transaction amount                                |
| `merchantId`        | `string`      | Merchant ID echoed back by SADAD                  |
| `message`           | `string`      | Human-readable status message                     |
| `isTestMode`        | `bool`        | Whether the transaction was processed in test mode|
| `invoiceNumber`     | `string\|null`| Invoice number if applicable                      |

## Payment Callback

Handle the customer redirect back to your site after payment:

```php
// v1.1 callback
$result = $client->handleCallback($_POST, 'v1.1');

// v2.1 or v2.2 callback
$result = $client->handleCallback($_POST, 'v2.1');

if ($result->isSuccess) {
    // Payment successful — update order status
}
```

`CallbackResult` properties:

| Property            | Type     | Description                        |
|---------------------|----------|------------------------------------|
| `isSuccess`         | `bool`   | `true` when `RESPCODE === '1'`     |
| `orderNumber`       | `string` | Your original order ID             |
| `transactionNumber` | `string` | SADAD transaction reference        |
| `amount`            | `float`  | Transaction amount                 |
| `responseCode`      | `string` | SADAD response code                |
| `responseMessage`   | `string` | Human-readable response message    |
| `status`            | `string` | Raw transaction status string      |

## Refunds

> **Important:** SADAD supports **full refunds only**. Partial refund amounts are not accepted. Refunds must be requested within **3 months** of the original transaction date.

```php
try {
    $result = $client->refund('TXN-123456789');

    if ($result['success']) {
        // Refund accepted
        var_dump($result['refund_details']);
    }
} catch (\LouisInnovations\Sadad\Exceptions\RefundException $e) {
    // $e->getCode() returns: REFUND_NOT_FOUND | REFUND_INVALID_STATUS
    //                        | REFUND_EXPIRED | REFUND_ALREADY_DONE
    echo 'Refund failed: ' . $e->getMessage();
}
```

## Invoice Management

### Create an invoice

```php
$result = $client->createInvoice([
    'cellnumber'     => '97412345678',
    'clientname'     => 'Ahmed Al-Farsi',
    'remarks'        => 'Consulting services - March 2026',
    'amount'         => 500.00,
    'invoicedetails' => [
        ['description' => 'Consulting', 'amount' => 500.00, 'quantity' => 1],
    ],
]);

if ($result['success']) {
    $invoiceNumber = $result['invoice_number'];
}
```

### Share an invoice

```php
// Via email
$client->shareInvoice($invoiceNumber, 'email', 'client@example.com');

// Via SMS
$client->shareInvoice($invoiceNumber, 'sms', '97412345678');
```

### List invoices

```php
$result = $client->listInvoices([
    'skip'  => 0,
    'limit' => 20,
    'status' => 2, // 2 = Unpaid
]);

$invoices = $result['invoices'];
```

## Transaction Lookup

```php
$result = $client->getTransaction('TXN-123456789');

if ($result['success']) {
    $transaction = $result['transaction'];
}
```

## Error Handling

All SDK exceptions extend `LouisInnovations\Sadad\Exceptions\SadadException`.

| Exception                | Thrown when                                                  |
|--------------------------|--------------------------------------------------------------|
| `SadadException`         | Base exception — unexpected SDK errors                       |
| `AuthenticationException`| SADAD API login fails or returns no access token             |
| `SignatureException`      | Webhook or callback signature verification fails             |
| `RefundException`         | Refund eligibility check fails (invalid status, expired, etc)|

```php
use LouisInnovations\Sadad\Exceptions\AuthenticationException;
use LouisInnovations\Sadad\Exceptions\RefundException;
use LouisInnovations\Sadad\Exceptions\SadadException;
use LouisInnovations\Sadad\Exceptions\SignatureException;

try {
    $result = $client->handleWebhook($payload);
} catch (SignatureException $e) {
    // Webhook signature invalid — reject the request
    http_response_code(400);
} catch (SadadException $e) {
    // General SDK error
    error_log($e->getMessage());
}
```

## Testing

```bash
# Run the full test suite
composer test

# Or directly via PHPUnit
vendor/bin/phpunit

# With verbose test names
vendor/bin/phpunit --testdox
```

The test suite covers 196 test cases across all modules. All tests run against mocked HTTP clients — no real SADAD credentials are required.

## Troubleshooting

**"Merchant ID must be exactly 7 digits"**
Ensure your merchant ID is exactly 7 numeric digits (e.g. `7015085`). Do not include spaces or dashes.

**"No access token in response"**
Check that your `merchantId`, `secretKey`, and `website` exactly match the values registered at [panel.sadad.qa](https://panel.sadad.qa). Also verify your environment is set to `'test'` while testing.

**"Signature verification failed" on webhook/callback**
Confirm the `secretKey` in `SadadConfig` is identical to the key configured in the SADAD merchant panel. Also ensure the raw POST body is passed without any modification.

**"Transaction is older than 3 months and cannot be refunded"**
SADAD only allows refunds within 90 days of the original transaction date.

**"Transaction has already been refunded"**
Each transaction can only be refunded once.

**`ext-openssl` not found**
Enable the OpenSSL extension in your `php.ini` — the v2 checksum and AES encryption features require it.

## Bug Reports

Please open an issue on [GitHub Issues](https://github.com/louis-innovations/sadad-php-sdk/issues) or email [info@louis-innovations.com](mailto:info@louis-innovations.com).

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a pull request.

## License

This project is licensed under the [MIT License](LICENSE).

---

Built by [Louis Innovations](https://www.louis-innovations.com)
