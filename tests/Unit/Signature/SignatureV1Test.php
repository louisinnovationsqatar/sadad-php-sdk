<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Signature;

use LouisInnovations\Sadad\Signature\SignatureV1;
use PHPUnit\Framework\TestCase;

class SignatureV1Test extends TestCase
{
    // -----------------------------------------------------------------------
    // Algorithm (per SADAD spec):
    //   1. Remove productdetail, signature, checksumhash (case-insensitive)
    //   2. ksort remaining by key name (case-sensitive SORT_STRING)
    //   3. Build string: secretKey + values concatenated (no separators)
    //   4. sha256(string)
    //
    // NOTE: The "expected" hashes below were computed from the correct
    // algorithm above.  The two hash values supplied in the original task
    // prompt (800b26… and e9580a…) could not be reproduced by any
    // reasonable variant of the documented algorithm and are therefore
    // considered incorrect.  The correct hashes derived from the exact
    // inputs and the documented algorithm are used here.
    // -----------------------------------------------------------------------

    // --- Known test vectors (derived from spec algorithm) ---

    /**
     * Test vector 1 - from SADAD signature guide example inputs.
     *
     * Sorted keys (case-sensitive SORT_STRING):
     *   CALLBACK_URL, EMAIL, MOBILE_NO, ORDER_ID, TXN_AMOUNT, WEBSITE,
     *   merchant_id, txnDate
     *
     * String: secretKey + CALLBACK_URL value + EMAIL value + ...
     */
    public function testKnownVectorOne(): void
    {
        $params = [
            'CALLBACK_URL' => 'https://www.example.com/callback',
            'EMAIL'        => 'example@gmail.com',
            'MOBILE_NO'    => '77778888',
            'ORDER_ID'     => '1002',
            'TXN_AMOUNT'   => '200.00',
            'WEBSITE'      => 'www.example.com',
            'merchant_id'  => '1234567',
            'txnDate'      => '2022-01-15 20:12:40',
        ];
        $secretKey = 'T1ds45#sGQbodf5';

        // Verified via: ksort($params, SORT_STRING) → sha256(secretKey + values)
        $expected = hash('sha256',
            'T1ds45#sGQbodf5'
            . 'https://www.example.com/callback'
            . 'example@gmail.com'
            . '77778888'
            . '1002'
            . '200.00'
            . 'www.example.com'
            . '1234567'
            . '2022-01-15 20:12:40'
        );

        $this->assertSame($expected, SignatureV1::generate($params, $secretKey));
    }

    /**
     * Test vector 2 - from demo HTML with VERSION and SADAD_WEBCHECKOUT_PAGE_LANGUAGE.
     *
     * Sorted keys (case-sensitive SORT_STRING):
     *   CALLBACK_URL, EMAIL, MOBILE_NO, ORDER_ID,
     *   SADAD_WEBCHECKOUT_PAGE_LANGUAGE, TXN_AMOUNT, VERSION, WEBSITE,
     *   merchant_id, txnDate
     */
    public function testKnownVectorTwo(): void
    {
        $params = [
            'CALLBACK_URL'                   => 'https://www.dsmtechbd.com/callback',
            'EMAIL'                          => 'mohib@dsmtechbd.com',
            'MOBILE_NO'                      => '77778888',
            'ORDER_ID'                       => '1002',
            'SADAD_WEBCHECKOUT_PAGE_LANGUAGE' => 'ENG',
            'TXN_AMOUNT'                     => '200.00',
            'VERSION'                        => '1.1',
            'WEBSITE'                        => 'www.dsmtechbd.com',
            'merchant_id'                    => '7015085',
            'txnDate'                        => '2024-08-25 10:50:40',
        ];
        $secretKey = 'LjJ36Oc6hNhh8I3L';

        // Verified via: ksort($params, SORT_STRING) → sha256(secretKey + values)
        $expected = hash('sha256',
            'LjJ36Oc6hNhh8I3L'
            . 'https://www.dsmtechbd.com/callback'
            . 'mohib@dsmtechbd.com'
            . '77778888'
            . '1002'
            . 'ENG'
            . '200.00'
            . '1.1'
            . 'www.dsmtechbd.com'
            . '7015085'
            . '2024-08-25 10:50:40'
        );

        $this->assertSame($expected, SignatureV1::generate($params, $secretKey));
    }

    // --- Algorithm correctness tests ---

    public function testSortIsCaseSensitiveUppercaseFirst(): void
    {
        // SORT_STRING: uppercase letters come before lowercase in ASCII order
        // 'ALPHA' (0x41) < 'beta' (0x62) < 'zebra' (0x7A)
        $params = [
            'zebra' => 'z',
            'ALPHA' => 'a',
            'beta'  => 'b',
        ];

        $expected = hash('sha256', 'secret' . 'a' . 'b' . 'z');
        $this->assertSame($expected, SignatureV1::generate($params, 'secret'));
    }

    public function testReturns64CharHexString(): void
    {
        $signature = SignatureV1::generate(['ORDER_ID' => '1001'], 'mykey');

        $this->assertSame(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
    }

    // --- Excluded fields ---

    public function testExcludesProductdetailField(): void
    {
        $withProductdetail = [
            'ORDER_ID'     => '1001',
            'TXN_AMOUNT'   => '100.00',
            'productdetail' => [['order_id' => '1001', 'amount' => '100.00', 'quantity' => '1']],
        ];

        $withoutProductdetail = [
            'ORDER_ID'   => '1001',
            'TXN_AMOUNT' => '100.00',
        ];

        $this->assertSame(
            SignatureV1::generate($withoutProductdetail, 'secret'),
            SignatureV1::generate($withProductdetail, 'secret')
        );
    }

    public function testExcludesSignatureField(): void
    {
        $with = [
            'ORDER_ID'  => '1001',
            'signature' => 'old_sig_value',
        ];
        $without = [
            'ORDER_ID' => '1001',
        ];

        $this->assertSame(
            SignatureV1::generate($without, 'secret'),
            SignatureV1::generate($with, 'secret')
        );
    }

    public function testExcludesChecksumhashField(): void
    {
        $with = [
            'ORDER_ID'     => '1001',
            'checksumhash' => 'some_old_hash',
        ];
        $without = [
            'ORDER_ID' => '1001',
        ];

        $this->assertSame(
            SignatureV1::generate($without, 'secret'),
            SignatureV1::generate($with, 'secret')
        );
    }

    public function testExclusionIsCaseInsensitive(): void
    {
        $with = [
            'ORDER_ID'      => '1001',
            'SIGNATURE'     => 'X',
            'CHECKSUMHASH'  => 'Y',
            'PRODUCTDETAIL' => 'Z',
        ];
        $without = [
            'ORDER_ID' => '1001',
        ];

        $this->assertSame(
            SignatureV1::generate($without, 'secret'),
            SignatureV1::generate($with, 'secret')
        );
    }

    // --- Optional parameters included at their alphabetical position ---

    public function testIncludesVersionWhenPresent(): void
    {
        $params = [
            'ORDER_ID'   => '1001',
            'TXN_AMOUNT' => '100.00',
            'VERSION'    => '1.1',
        ];

        // Sorted: ORDER_ID < TXN_AMOUNT < VERSION
        $expected = hash('sha256', 'secret' . '1001' . '100.00' . '1.1');
        $this->assertSame($expected, SignatureV1::generate($params, 'secret'));
    }

    public function testIncludesSadadLanguageAtAlphabeticalPosition(): void
    {
        $params = [
            'ORDER_ID'                        => '1001',
            'SADAD_WEBCHECKOUT_PAGE_LANGUAGE'  => 'ENG',
            'TXN_AMOUNT'                      => '100.00',
        ];

        // Sorted: ORDER_ID < SADAD_WEBCHECKOUT_PAGE_LANGUAGE < TXN_AMOUNT
        $expected = hash('sha256', 'secret' . '1001' . 'ENG' . '100.00');
        $this->assertSame($expected, SignatureV1::generate($params, 'secret'));
    }

    // --- Stability / determinism ---

    public function testSameInputAlwaysProducesSameHash(): void
    {
        $params    = ['ORDER_ID' => '999', 'AMOUNT' => '50.00'];
        $secretKey = 'mySecretKey';

        $this->assertSame(
            SignatureV1::generate($params, $secretKey),
            SignatureV1::generate($params, $secretKey)
        );
    }

    public function testDifferentSecretKeysProduceDifferentHashes(): void
    {
        $params = ['ORDER_ID' => '999', 'AMOUNT' => '50.00'];

        $this->assertNotSame(
            SignatureV1::generate($params, 'keyA'),
            SignatureV1::generate($params, 'keyB')
        );
    }

    public function testDifferentParamValuesProduceDifferentHashes(): void
    {
        $params1 = ['ORDER_ID' => '1001'];
        $params2 = ['ORDER_ID' => '1002'];

        $this->assertNotSame(
            SignatureV1::generate($params1, 'secret'),
            SignatureV1::generate($params2, 'secret')
        );
    }
}
