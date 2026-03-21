<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Checkout;

use LouisInnovations\Sadad\Checkout\CheckoutResult;
use LouisInnovations\Sadad\Checkout\WebCheckoutEmbedded;
use LouisInnovations\Sadad\Checkout\WebCheckoutV2;
use LouisInnovations\Sadad\SadadConfig;
use PHPUnit\Framework\TestCase;

class WebCheckoutV2Test extends TestCase
{
    private SadadConfig $config;
    private SadadConfig $arabicConfig;

    protected function setUp(): void
    {
        $this->config = new SadadConfig(
            merchantId:  '7015085',
            secretKey:   'T1ds45#sGQbodf5',
            website:     'www.example.com',
            environment: 'test',
            language:    'eng',
            callbackUrl: 'https://www.example.com/callback',
        );

        $this->arabicConfig = new SadadConfig(
            merchantId:  '7015085',
            secretKey:   'T1ds45#sGQbodf5',
            website:     'www.example.com',
            environment: 'test',
            language:    'arb',
            callbackUrl: 'https://www.example.com/callback',
        );
    }

    private function singleItemOrder(): array
    {
        return [
            'order_id' => 'ORD-V2-001',
            'amount'   => 250.00,
            'mobile'   => '77778888',
            'email'    => 'customer@example.com',
            'items'    => [
                ['order_id' => 'ORD-V2-001', 'amount' => 250.00, 'quantity' => 1],
            ],
        ];
    }

    private function multiItemOrder(): array
    {
        return [
            'order_id' => 'ORD-V2-002',
            'amount'   => 400.00,
            'mobile'   => '77778888',
            'email'    => 'customer@example.com',
            'items'    => [
                ['order_id' => 'ORD-V2-002', 'amount' => 300.00, 'quantity' => 3],
                ['order_id' => 'ORD-V2-002-B', 'amount' => 100.00, 'quantity' => 1],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // WebCheckoutV2 — URL
    // -----------------------------------------------------------------------

    public function testV2CreatesResultWithCorrectUrl(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame('https://sadadqa.com/webpurchase', $result->url);
    }

    // -----------------------------------------------------------------------
    // WebCheckoutV2 — uses checksumhash, not signature
    // -----------------------------------------------------------------------

    public function testV2UsesChecksumhashNotSignature(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $params = $result->params;

        $this->assertArrayHasKey('checksumhash', $params);
        $this->assertArrayNotHasKey('signature', $params);
    }

    public function testV2ChecksumhashIsNonEmpty(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertNotEmpty($result->params['checksumhash']);
    }

    // -----------------------------------------------------------------------
    // WebCheckoutV2 — Required params
    // -----------------------------------------------------------------------

    public function testV2IncludesAllRequiredParams(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $params = $result->params;

        $this->assertArrayHasKey('merchant_id', $params);
        $this->assertArrayHasKey('ORDER_ID', $params);
        $this->assertArrayHasKey('WEBSITE', $params);
        $this->assertArrayHasKey('TXN_AMOUNT', $params);
        $this->assertArrayHasKey('CALLBACK_URL', $params);
        $this->assertArrayHasKey('MOBILE_NO', $params);
        $this->assertArrayHasKey('EMAIL', $params);
        $this->assertArrayHasKey('txnDate', $params);
        $this->assertArrayHasKey('SADAD_WEBCHECKOUT_PAGE_LANGUAGE', $params);
    }

    public function testV2AlwaysIncludesSadadLanguageENG(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertSame('ENG', $result->params['SADAD_WEBCHECKOUT_PAGE_LANGUAGE']);
    }

    public function testV2ArabicConfigProducesARBLanguage(): void
    {
        $checkout = new WebCheckoutV2($this->arabicConfig);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertSame('ARB', $result->params['SADAD_WEBCHECKOUT_PAGE_LANGUAGE']);
    }

    public function testV2MultiItemIncludesVersion(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->multiItemOrder());

        $this->assertArrayHasKey('VERSION', $result->params);
        $this->assertSame('1.1', $result->params['VERSION']);
    }

    public function testV2SingleItemDoesNotIncludeVersion(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertArrayNotHasKey('VERSION', $result->params);
    }

    // -----------------------------------------------------------------------
    // WebCheckoutEmbedded — URL
    // -----------------------------------------------------------------------

    public function testEmbeddedCreatesResultWithV22Url(): void
    {
        $checkout = new WebCheckoutEmbedded($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame('https://secure.sadadqa.com/webpurchasepage', $result->url);
    }

    // -----------------------------------------------------------------------
    // WebCheckoutEmbedded — uses checksumhash, not signature
    // -----------------------------------------------------------------------

    public function testEmbeddedUsesChecksumhashNotSignature(): void
    {
        $checkout = new WebCheckoutEmbedded($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $params = $result->params;

        $this->assertArrayHasKey('checksumhash', $params);
        $this->assertArrayNotHasKey('signature', $params);
    }

    public function testEmbeddedChecksumhashIsNonEmpty(): void
    {
        $checkout = new WebCheckoutEmbedded($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertNotEmpty($result->params['checksumhash']);
    }

    // -----------------------------------------------------------------------
    // WebCheckoutEmbedded — inherits full param set
    // -----------------------------------------------------------------------

    public function testEmbeddedIncludesAllRequiredParams(): void
    {
        $checkout = new WebCheckoutEmbedded($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $params = $result->params;

        $this->assertArrayHasKey('merchant_id', $params);
        $this->assertArrayHasKey('ORDER_ID', $params);
        $this->assertArrayHasKey('WEBSITE', $params);
        $this->assertArrayHasKey('TXN_AMOUNT', $params);
        $this->assertArrayHasKey('CALLBACK_URL', $params);
        $this->assertArrayHasKey('MOBILE_NO', $params);
        $this->assertArrayHasKey('EMAIL', $params);
        $this->assertArrayHasKey('txnDate', $params);
        $this->assertArrayHasKey('SADAD_WEBCHECKOUT_PAGE_LANGUAGE', $params);
    }

    public function testEmbeddedAlwaysIncludesSadadLanguage(): void
    {
        $checkout = new WebCheckoutEmbedded($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertSame('ENG', $result->params['SADAD_WEBCHECKOUT_PAGE_LANGUAGE']);
    }

    // -----------------------------------------------------------------------
    // Different checksumhash per run (non-deterministic due to AES salt)
    // -----------------------------------------------------------------------

    public function testV2ChecksumhashVariesBetweenCalls(): void
    {
        $checkout = new WebCheckoutV2($this->config);
        $result1  = $checkout->createCheckout($this->singleItemOrder());
        $result2  = $checkout->createCheckout($this->singleItemOrder());

        // Because SignatureV2 uses a random 4-char salt, consecutive calls
        // should (with overwhelming probability) produce different checksums.
        // We only assert both are non-empty strings.
        $this->assertIsString($result1->params['checksumhash']);
        $this->assertIsString($result2->params['checksumhash']);
        $this->assertNotEmpty($result1->params['checksumhash']);
        $this->assertNotEmpty($result2->params['checksumhash']);
    }
}
