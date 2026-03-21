<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Invoice;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\SadadConfig;

class InvoiceManager
{
    /** sentvia value for email sharing. */
    private const SHARE_VIA_EMAIL = 3;

    /** sentvia value for SMS sharing. */
    private const SHARE_VIA_SMS = 4;

    /** Default invoice status: Unpaid. */
    private const STATUS_UNPAID = 2;

    /** Default country code for Qatar. */
    private const DEFAULT_COUNTRY_CODE = 974;

    public function __construct(
        private SadadConfig $config,
        private HttpClientInterface $httpClient,
        private Authenticator $authenticator,
    ) {}

    /**
     * Create a new invoice.
     *
     * Expected keys in $data:
     *   - cellnumber  (string) Customer mobile number (will be stripped to digits only)
     *   - clientname  (string) Customer name
     *   - remarks     (string) Invoice remarks / description
     *   - amount      (float)  Invoice total amount
     *   - invoicedetails (array) Line items
     *   - countryCode (int, optional) Defaults to 974
     *   - status      (int, optional) Defaults to 2 (Unpaid)
     *
     * @param  array<string, mixed> $data Invoice data.
     * @return array<string, mixed>       Response with 'success', 'invoice_number', 'invoice_id', 'data' or 'error'.
     */
    public function createInvoice(array $data): array
    {
        try {
            $token = $this->authenticator->getAccessToken();

            $payload = [
                'countryCode'    => (int) ($data['countryCode'] ?? self::DEFAULT_COUNTRY_CODE),
                'cellnumber'     => preg_replace('/\D/', '', (string) ($data['cellnumber'] ?? '')),
                'clientname'     => $data['clientname'] ?? '',
                'status'         => (int) ($data['status'] ?? self::STATUS_UNPAID),
                'remarks'        => $data['remarks'] ?? '',
                'amount'         => (float) ($data['amount'] ?? 0.0),
                'invoicedetails' => $data['invoicedetails'] ?? [],
            ];

            $response = $this->httpClient->post(
                $this->config->getApiBaseUrl() . '/invoices/createInvoice',
                $payload,
                ['Authorization' => 'Bearer ' . $token]
            );

            return [
                'success'        => true,
                'invoice_number' => $response['invoiceNumber'] ?? $response['invoice_number'] ?? null,
                'invoice_id'     => $response['invoiceId']     ?? $response['invoice_id']     ?? null,
                'data'           => $response,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Share an existing invoice via email or SMS.
     *
     * @param  string               $invoiceNumber The invoice number to share.
     * @param  string               $method        'email' or 'sms'.
     * @param  string               $recipient     Email address or mobile number.
     * @return array<string, mixed>                Response with 'success' and 'message' or 'error'.
     */
    public function shareInvoice(string $invoiceNumber, string $method, string $recipient): array
    {
        try {
            $token = $this->authenticator->getAccessToken();

            $isEmail = strtolower($method) === 'email';

            $payload = [
                'invoiceNumber' => $invoiceNumber,
                'sentvia'       => $isEmail ? self::SHARE_VIA_EMAIL : self::SHARE_VIA_SMS,
            ];

            if ($isEmail) {
                $payload['receiverEmail'] = $recipient;
            } else {
                $payload['receivercellno'] = preg_replace('/\D/', '', $recipient);
            }

            $response = $this->httpClient->post(
                $this->config->getApiBaseUrl() . '/invoices/share',
                $payload,
                ['Authorization' => 'Bearer ' . $token]
            );

            return [
                'success' => true,
                'message' => $response['message'] ?? 'Invoice shared successfully.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * List invoices with optional filters.
     *
     * Supported keys in $filters:
     *   - skip           (int)
     *   - limit          (int)
     *   - status         (int)
     *   - clientname     (string)
     *   - invoicenumber  (string)
     *   - date           (string)
     *
     * @param  array<string, mixed> $filters Optional query filters.
     * @return array<string, mixed>          Response with 'success' and 'invoices' or 'error'.
     */
    public function listInvoices(array $filters = []): array
    {
        try {
            $token = $this->authenticator->getAccessToken();

            $params = [];
            $supportedFilters = ['skip', 'limit', 'status', 'clientname', 'invoicenumber', 'date'];
            foreach ($supportedFilters as $key) {
                if (isset($filters[$key])) {
                    $params['filter[' . $key . ']'] = $filters[$key];
                }
            }

            $response = $this->httpClient->get(
                $this->config->getApiBaseUrl() . '/invoices/listInvoices',
                $params,
                ['Authorization' => 'Bearer ' . $token]
            );

            return [
                'success'  => true,
                'invoices' => $response['invoices'] ?? $response['data'] ?? $response,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
