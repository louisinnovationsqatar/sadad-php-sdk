<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Http;

interface HttpClientInterface
{
    /**
     * Send a POST request and return the decoded JSON response.
     *
     * @param  string               $url     Fully qualified URL.
     * @param  array<string, mixed> $data    Request body (JSON-encoded).
     * @param  array<string, mixed> $headers Additional HTTP headers.
     * @return array<string, mixed>          Decoded JSON response body.
     */
    public function post(string $url, array $data = [], array $headers = []): array;

    /**
     * Send a GET request and return the decoded JSON response.
     *
     * @param  string               $url     Fully qualified URL.
     * @param  array<string, mixed> $params  Query string parameters.
     * @param  array<string, mixed> $headers Additional HTTP headers.
     * @return array<string, mixed>          Decoded JSON response body.
     */
    public function get(string $url, array $params = [], array $headers = []): array;
}
