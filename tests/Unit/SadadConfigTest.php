<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit;

use LouisInnovations\Sadad\SadadConfig;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class SadadConfigTest extends TestCase
{
    private function validConfig(array $overrides = []): SadadConfig
    {
        $defaults = [
            'merchantId'  => '1234567',
            'secretKey'   => 'T1ds45#sGQbodf5',
            'website'     => 'www.example.com',
        ];

        $params = array_merge($defaults, $overrides);

        return new SadadConfig(
            merchantId:  $params['merchantId'],
            secretKey:   $params['secretKey'],
            website:     $params['website'],
            environment: $params['environment'] ?? 'test',
            language:    $params['language'] ?? 'eng',
            callbackUrl: $params['callbackUrl'] ?? null,
            webhookUrl:  $params['webhookUrl'] ?? null,
        );
    }

    // --- Valid config creation ---

    public function testValidConfigCreation(): void
    {
        $config = $this->validConfig();

        $this->assertSame('1234567', $config->merchantId);
        $this->assertSame('T1ds45#sGQbodf5', $config->secretKey);
        $this->assertSame('www.example.com', $config->website);
        $this->assertSame('test', $config->environment);
        $this->assertSame('eng', $config->language);
        $this->assertNull($config->callbackUrl);
        $this->assertNull($config->webhookUrl);
    }

    public function testConfigWithAllOptionalFields(): void
    {
        $config = $this->validConfig([
            'environment' => 'live',
            'language'    => 'arb',
            'callbackUrl' => 'https://example.com/callback',
            'webhookUrl'  => 'https://example.com/webhook',
        ]);

        $this->assertSame('live', $config->environment);
        $this->assertSame('arb', $config->language);
        $this->assertSame('https://example.com/callback', $config->callbackUrl);
        $this->assertSame('https://example.com/webhook', $config->webhookUrl);
    }

    // --- Defaults ---

    public function testDefaultsToTestEnvironment(): void
    {
        $config = new SadadConfig(
            merchantId: '1234567',
            secretKey:  'secret',
            website:    'www.example.com',
        );

        $this->assertSame('test', $config->environment);
    }

    public function testDefaultsToEngLanguage(): void
    {
        $config = new SadadConfig(
            merchantId: '1234567',
            secretKey:  'secret',
            website:    'www.example.com',
        );

        $this->assertSame('eng', $config->language);
    }

    // --- Merchant ID validation ---

    public function testRejectsNonSevenDigitMerchantIdTooShort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['merchantId' => '123456']);
    }

    public function testRejectsNonSevenDigitMerchantIdTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['merchantId' => '12345678']);
    }

    public function testRejectsNonNumericMerchantId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['merchantId' => 'abc1234']);
    }

    public function testRejectsEmptyMerchantId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['merchantId' => '']);
    }

    // --- Secret key validation ---

    public function testRejectsEmptySecretKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['secretKey' => '']);
    }

    // --- Environment validation ---

    public function testRejectsInvalidEnvironment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['environment' => 'production']);
    }

    public function testAcceptsTestEnvironment(): void
    {
        $config = $this->validConfig(['environment' => 'test']);
        $this->assertSame('test', $config->environment);
    }

    public function testAcceptsLiveEnvironment(): void
    {
        $config = $this->validConfig(['environment' => 'live']);
        $this->assertSame('live', $config->environment);
    }

    // --- Language validation ---

    public function testRejectsInvalidLanguage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validConfig(['language' => 'fr']);
    }

    public function testAcceptsEngLanguage(): void
    {
        $config = $this->validConfig(['language' => 'eng']);
        $this->assertSame('eng', $config->language);
    }

    public function testAcceptsArbLanguage(): void
    {
        $config = $this->validConfig(['language' => 'arb']);
        $this->assertSame('arb', $config->language);
    }

    // --- Checkout URLs ---

    public function testCheckoutUrlV11(): void
    {
        $config = $this->validConfig();
        $this->assertSame('https://sadadqa.com/webpurchase', $config->getCheckoutUrl('v1.1'));
    }

    public function testCheckoutUrlV21(): void
    {
        $config = $this->validConfig();
        $this->assertSame('https://sadadqa.com/webpurchase', $config->getCheckoutUrl('v2.1'));
    }

    public function testCheckoutUrlV22(): void
    {
        $config = $this->validConfig();
        $this->assertSame('https://secure.sadadqa.com/webpurchasepage', $config->getCheckoutUrl('v2.2'));
    }

    // --- API base URL ---

    public function testApiBaseUrl(): void
    {
        $config = $this->validConfig();
        $this->assertSame('https://api-s.sadad.qa/api', $config->getApiBaseUrl());
    }

    // --- Same URLs for test and live environments ---

    public function testSameCheckoutUrlForTestAndLive(): void
    {
        $testConfig = $this->validConfig(['environment' => 'test']);
        $liveConfig = $this->validConfig(['environment' => 'live']);

        $this->assertSame($testConfig->getCheckoutUrl('v1.1'), $liveConfig->getCheckoutUrl('v1.1'));
        $this->assertSame($testConfig->getCheckoutUrl('v2.1'), $liveConfig->getCheckoutUrl('v2.1'));
        $this->assertSame($testConfig->getCheckoutUrl('v2.2'), $liveConfig->getCheckoutUrl('v2.2'));
    }

    public function testSameApiBaseUrlForTestAndLive(): void
    {
        $testConfig = $this->validConfig(['environment' => 'test']);
        $liveConfig = $this->validConfig(['environment' => 'live']);

        $this->assertSame($testConfig->getApiBaseUrl(), $liveConfig->getApiBaseUrl());
    }
}
