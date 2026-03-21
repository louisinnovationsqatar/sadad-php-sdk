<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Signature;

class SignatureV1
{
    /**
     * Keys that must be stripped from the parameter set before signing.
     * Comparison is case-insensitive (stored lowercase).
     */
    private const EXCLUDED_KEYS = ['productdetail', 'signature', 'checksumhash'];

    /**
     * Generate a SHA-256 signature for the given parameters.
     *
     * Algorithm (SADAD v1.1 spec):
     *   1. Remove productdetail, signature, and checksumhash (case-insensitive).
     *   2. Sort the remaining parameters by key name using case-sensitive
     *      alphabetical ordering (ksort with SORT_STRING — uppercase before
     *      lowercase, matching ASCII order).
     *   3. Construct the string: secretKey + value1 + value2 + ...
     *      (values only, in sorted-key order, no separators).
     *   4. Return sha256(string) as a lowercase hex string.
     *
     * @param  array<string, mixed> $params    Checkout / callback parameters.
     * @param  string               $secretKey Merchant secret key.
     * @return string                          64-character lowercase hex SHA-256 hash.
     */
    public static function generate(array $params, string $secretKey): string
    {
        // Step 1 — Remove excluded keys (case-insensitive comparison)
        $filtered = [];
        foreach ($params as $key => $value) {
            if (!in_array(strtolower($key), self::EXCLUDED_KEYS, true)) {
                $filtered[$key] = $value;
            }
        }

        // Step 2 — Case-sensitive alphabetical sort (SORT_STRING)
        ksort($filtered, SORT_STRING);

        // Step 3 — Build the string to hash
        $string = $secretKey;
        foreach ($filtered as $value) {
            $string .= (string) $value;
        }

        // Step 4 — SHA-256
        return hash('sha256', $string);
    }
}
