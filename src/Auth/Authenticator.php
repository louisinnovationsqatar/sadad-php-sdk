<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Auth;

use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\Exceptions\AuthenticationException;

class Authenticator
{
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;

    public function __construct(
        private SadadConfig $config,
        private HttpClientInterface $httpClient,
    ) {}

    public function getAccessToken(): string
    {
        if ($this->accessToken !== null && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }
        return $this->login();
    }

    public function login(): string
    {
        try {
            $response = $this->httpClient->post(
                $this->config->getApiBaseUrl() . '/userbusinesses/login',
                [
                    'sadadId'   => (int) $this->config->merchantId,
                    'secretKey' => $this->config->secretKey,
                    'domain'    => $this->config->website,
                ]
            );

            if (empty($response['accessToken'])) {
                throw new AuthenticationException('No access token in response');
            }

            $this->accessToken = $response['accessToken'];
            $this->tokenExpiry = time() + 3600; // 1 hour cache

            return $this->accessToken;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AuthenticationException(
                'Authentication failed: ' . $e->getMessage(),
                null,
                $e
            );
        }
    }
}
