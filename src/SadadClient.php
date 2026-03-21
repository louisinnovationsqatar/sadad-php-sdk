<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Checkout\CheckoutResult;
use LouisInnovations\Sadad\Checkout\WebCheckoutEmbedded;
use LouisInnovations\Sadad\Checkout\WebCheckoutV1;
use LouisInnovations\Sadad\Checkout\WebCheckoutV2;
use LouisInnovations\Sadad\Callback\CallbackHandler;
use LouisInnovations\Sadad\Callback\CallbackResult;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\Http\GuzzleHttpClient;
use LouisInnovations\Sadad\Invoice\InvoiceManager;
use LouisInnovations\Sadad\Refund\RefundManager;
use LouisInnovations\Sadad\Transaction\TransactionManager;
use LouisInnovations\Sadad\Webhook\WebhookHandler;
use LouisInnovations\Sadad\Webhook\WebhookResult;

class SadadClient
{
    private SadadConfig $config;
    private HttpClientInterface $httpClient;
    private Authenticator $authenticator;
    private InvoiceManager $invoiceManager;
    private RefundManager $refundManager;
    private TransactionManager $transactionManager;
    private WebhookHandler $webhookHandler;
    private CallbackHandler $callbackHandler;

    public function __construct(SadadConfig $config, ?HttpClientInterface $httpClient = null)
    {
        $this->config = $config;
        $this->httpClient = $httpClient ?? new GuzzleHttpClient();
        $this->authenticator = new Authenticator($config, $this->httpClient);
        $this->transactionManager = new TransactionManager($config, $this->httpClient, $this->authenticator);
        $this->invoiceManager = new InvoiceManager($config, $this->httpClient, $this->authenticator);
        $this->refundManager = new RefundManager($config, $this->httpClient, $this->authenticator, $this->transactionManager);
        $this->webhookHandler = new WebhookHandler($config);
        $this->callbackHandler = new CallbackHandler($config);
    }

    public function checkout(array $orderData, string $version = 'v1.1'): CheckoutResult
    {
        return match ($version) {
            'v1.1' => (new WebCheckoutV1($this->config))->createCheckout($orderData),
            'v2.1' => (new WebCheckoutV2($this->config))->createCheckout($orderData),
            'v2.2' => (new WebCheckoutEmbedded($this->config))->createCheckout($orderData),
            default => throw new \InvalidArgumentException("Invalid checkout version: {$version}"),
        };
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        return $this->webhookHandler->handle($payload);
    }

    public function handleCallback(array $postData, string $version = 'v1.1'): CallbackResult
    {
        return $this->callbackHandler->handle($postData, $version);
    }

    public function createInvoice(array $data): array
    {
        return $this->invoiceManager->createInvoice($data);
    }

    public function shareInvoice(string $invoiceNumber, string $method, string $recipient): array
    {
        return $this->invoiceManager->shareInvoice($invoiceNumber, $method, $recipient);
    }

    public function listInvoices(array $filters = []): array
    {
        return $this->invoiceManager->listInvoices($filters);
    }

    public function refund(string $transactionNumber): array
    {
        return $this->refundManager->refund($transactionNumber);
    }

    public function getTransaction(string $transactionNumber): array
    {
        return $this->transactionManager->getTransaction($transactionNumber);
    }

    public static function webhookSuccessResponse(): array
    {
        return WebhookHandler::successResponse();
    }
}
