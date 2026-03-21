<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad;

use InvalidArgumentException;

class SadadConfig
{
    private const CHECKOUT_URLS = [
        'v1.1' => 'https://sadadqa.com/webpurchase',
        'v2.1' => 'https://sadadqa.com/webpurchase',
        'v2.2' => 'https://secure.sadadqa.com/webpurchasepage',
    ];

    private const API_BASE_URL = 'https://api-s.sadad.qa/api';

    private const VALID_ENVIRONMENTS = ['test', 'live'];

    private const VALID_LANGUAGES = ['eng', 'arb'];

    public function __construct(
        public readonly string $merchantId,
        public readonly string $secretKey,
        public readonly string $website,
        public readonly string $environment = 'test',
        public readonly string $language = 'eng',
        public readonly ?string $callbackUrl = null,
        public readonly ?string $webhookUrl = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!preg_match('/^\d{7}$/', $this->merchantId)) {
            throw new InvalidArgumentException(
                'Merchant ID must be exactly 7 digits.'
            );
        }

        if ($this->secretKey === '') {
            throw new InvalidArgumentException(
                'Secret key cannot be empty.'
            );
        }

        if (!in_array($this->environment, self::VALID_ENVIRONMENTS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Environment must be one of: %s. Got: "%s".',
                    implode(', ', self::VALID_ENVIRONMENTS),
                    $this->environment
                )
            );
        }

        if (!in_array($this->language, self::VALID_LANGUAGES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Language must be one of: %s. Got: "%s".',
                    implode(', ', self::VALID_LANGUAGES),
                    $this->language
                )
            );
        }
    }

    public function getCheckoutUrl(string $version): string
    {
        if (!isset(self::CHECKOUT_URLS[$version])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown checkout version "%s". Supported versions: %s.',
                    $version,
                    implode(', ', array_keys(self::CHECKOUT_URLS))
                )
            );
        }

        return self::CHECKOUT_URLS[$version];
    }

    public function getApiBaseUrl(): string
    {
        return self::API_BASE_URL;
    }
}
