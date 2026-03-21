<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Transaction;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\SadadConfig;

class TransactionManager
{
    public function __construct(
        private SadadConfig $config,
        private HttpClientInterface $httpClient,
        private Authenticator $authenticator,
    ) {}

    /**
     * Retrieve transaction details by transaction number.
     *
     * @param  string               $transactionNumber The transaction number to look up.
     * @return array<string, mixed>                    Response with 'success' and 'transaction' or 'error'.
     */
    public function getTransaction(string $transactionNumber): array
    {
        try {
            $token = $this->authenticator->getAccessToken();

            $response = $this->httpClient->get(
                $this->config->getApiBaseUrl() . '/transactions/getTransaction',
                ['transactionno' => $transactionNumber],
                ['Authorization' => 'Bearer ' . $token]
            );

            return [
                'success'     => true,
                'transaction' => $response,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
