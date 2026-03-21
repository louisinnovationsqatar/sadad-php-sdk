<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LouisInnovations\Sadad\Exceptions\SadadException;

class GuzzleHttpClient implements HttpClientInterface
{
    private const DEFAULT_TIMEOUT = 30;

    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => self::DEFAULT_TIMEOUT,
        ]);
    }

    /**
     * Send a POST request with a JSON body and return the decoded response.
     *
     * @param  string               $url     Fully qualified URL.
     * @param  array<string, mixed> $data    Request body (JSON-encoded).
     * @param  array<string, mixed> $headers Additional HTTP headers.
     * @return array<string, mixed>          Decoded JSON response body.
     * @throws SadadException                When the HTTP request fails.
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'json'    => $data,
                'headers' => $headers,
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new SadadException(
                'HTTP POST request failed: ' . $e->getMessage(),
                'HTTP_ERROR'
            );
        }
    }

    /**
     * Send a GET request with query parameters and return the decoded response.
     *
     * @param  string               $url     Fully qualified URL.
     * @param  array<string, mixed> $params  Query string parameters.
     * @param  array<string, mixed> $headers Additional HTTP headers.
     * @return array<string, mixed>          Decoded JSON response body.
     * @throws SadadException                When the HTTP request fails.
     */
    public function get(string $url, array $params = [], array $headers = []): array
    {
        try {
            $response = $this->client->get($url, [
                'query'   => $params,
                'headers' => $headers,
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new SadadException(
                'HTTP GET request failed: ' . $e->getMessage(),
                'HTTP_ERROR'
            );
        }
    }
}
