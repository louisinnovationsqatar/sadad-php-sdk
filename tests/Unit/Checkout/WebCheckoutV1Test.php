<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Checkout;

use LouisInnovations\Sadad\Checkout\CheckoutResult;
use LouisInnovations\Sadad\Checkout\WebCheckoutV1;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureV1;
use PHPUnit\Framework\TestCase;

class WebCheckoutV1Test extends TestCase
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
            'order_id' => 'ORD-001',
            'amount'   => 100.00,
            'mobile'   => '+97477778888',
            'email'    => 'customer@example.com',
            'items'    => [
                ['order_id' => 'ORD-001', 'amount' => 100.00, 'quantity' => 1],
            ],
        ];
    }

    private function multiItemOrder(): array
    {
        return [
            'order_id' => 'ORD-002',
            'amount'   => 300.00,
            'mobile'   => '77778888',
            'email'    => 'customer@example.com',
            'items'    => [
                ['order_id' => 'ORD-002', 'amount' => 200.00, 'quantity' => 2],
                ['order_id' => 'ORD-002-B', 'amount' => 100.00, 'quantity' => 1],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // CheckoutResult URL
    // -----------------------------------------------------------------------

    public function testCreatesResultWithCorrectV11Url(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame('https://sadadqa.com/webpurchase', $result->url);
    }

    // -----------------------------------------------------------------------
    // Required params
    // -----------------------------------------------------------------------

    public function testIncludesAllRequiredParams(): void
    {
        $checkout = new WebCheckoutV1($this->config);
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
        $this->assertArrayHasKey('signature', $params);
    }

    public function testParamValuesAreCorrect(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $params = $result->params;

        $this->assertSame('7015085', $params['merchant_id']);
        $this->assertSame('ORD-001', $params['ORDER_ID']);
        $this->assertSame('www.example.com', $params['WEBSITE']);
        $this->assertSame('100.00', $params['TXN_AMOUNT']);
        $this->assertSame('https://www.example.com/callback', $params['CALLBACK_URL']);
        $this->assertSame('97477778888', $params['MOBILE_NO']);  // digits only
        $this->assertSame('customer@example.com', $params['EMAIL']);
    }

    public function testMobileNumberStripsNonDigits(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $order    = $this->singleItemOrder();
        $order['mobile'] = '+974 7777-8888';
        $result   = $checkout->createCheckout($order);

        $this->assertSame('97477778888', $result->params['MOBILE_NO']);
    }

    public function testAmountFormattedToTwoDecimalPlaces(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $order    = $this->singleItemOrder();
        $order['amount'] = 99.9;
        $result   = $checkout->createCheckout($order);

        $this->assertSame('99.90', $result->params['TXN_AMOUNT']);
    }

    // -----------------------------------------------------------------------
    // Language parameter
    // -----------------------------------------------------------------------

    public function testAlwaysIncludesSadadLanguageAsENG(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertSame('ENG', $result->params['SADAD_WEBCHECKOUT_PAGE_LANGUAGE']);
    }

    public function testArabicConfigProducesARBLanguage(): void
    {
        $checkout = new WebCheckoutV1($this->arabicConfig);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertSame('ARB', $result->params['SADAD_WEBCHECKOUT_PAGE_LANGUAGE']);
    }

    // -----------------------------------------------------------------------
    // VERSION for multi-product
    // -----------------------------------------------------------------------

    public function testSingleItemDoesNotIncludeVersion(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $this->assertArrayNotHasKey('VERSION', $result->params);
    }

    public function testMultiItemIncludesVersion11(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->multiItemOrder());

        $this->assertArrayHasKey('VERSION', $result->params);
        $this->assertSame('1.1', $result->params['VERSION']);
    }

    // -----------------------------------------------------------------------
    // Signature
    // -----------------------------------------------------------------------

    public function testSignatureIsValidV1Hash(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $params = $result->params;
        $receivedSignature = $params['signature'];

        // Rebuild expected signature excluding signature and productdetail
        $paramsForVerify = $params;
        unset($paramsForVerify['signature'], $paramsForVerify['productdetail']);

        $expected = SignatureV1::generate($paramsForVerify, $this->config->secretKey);

        $this->assertSame($expected, $receivedSignature);
    }

    // -----------------------------------------------------------------------
    // Product detail
    // -----------------------------------------------------------------------

    public function testProductDetailIsIncluded(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->multiItemOrder());

        $this->assertArrayHasKey('productdetail', $result->params);
        $productDetail = $result->params['productdetail'];

        $this->assertCount(2, $productDetail);
        $this->assertSame('ORD-002', $productDetail[0]['order_id']);
        $this->assertSame('200.00', $productDetail[0]['amount']);
        $this->assertSame('2', $productDetail[0]['quantity']);
    }

    // -----------------------------------------------------------------------
    // toHtmlForm
    // -----------------------------------------------------------------------

    public function testToHtmlFormGeneratesValidHtml(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $html = $result->toHtmlForm();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('action="https://sadadqa.com/webpurchase"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }

    public function testToHtmlFormContainsAllParams(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $html = $result->toHtmlForm();

        $this->assertStringContainsString('name="merchant_id"', $html);
        $this->assertStringContainsString('name="ORDER_ID"', $html);
        $this->assertStringContainsString('name="TXN_AMOUNT"', $html);
        $this->assertStringContainsString('name="CALLBACK_URL"', $html);
        $this->assertStringContainsString('name="signature"', $html);
    }

    public function testToHtmlFormGeneratesProductDetailIndexedInputs(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->multiItemOrder());

        $html = $result->toHtmlForm();

        $this->assertStringContainsString('name="productdetail[0][order_id]"', $html);
        $this->assertStringContainsString('name="productdetail[0][amount]"', $html);
        $this->assertStringContainsString('name="productdetail[0][quantity]"', $html);
        $this->assertStringContainsString('name="productdetail[1][order_id]"', $html);
    }

    public function testToHtmlFormCustomFormId(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $html = $result->toHtmlForm('my-form', false);

        $this->assertStringContainsString('id="my-form"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testToHtmlFormAutoSubmitIncludesScript(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $html = $result->toHtmlForm('sadad-checkout-form', true);

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('sadad-checkout-form', $html);
        $this->assertStringContainsString('.submit()', $html);
    }

    public function testToHtmlFormNoAutoSubmitWhenDisabled(): void
    {
        $checkout = new WebCheckoutV1($this->config);
        $result   = $checkout->createCheckout($this->singleItemOrder());

        $html = $result->toHtmlForm('sadad-checkout-form', false);

        $this->assertStringNotContainsString('<script>', $html);
    }
}
