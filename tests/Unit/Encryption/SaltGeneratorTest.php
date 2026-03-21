<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Encryption;

use LouisInnovations\Sadad\Encryption\SaltGenerator;
use PHPUnit\Framework\TestCase;

class SaltGeneratorTest extends TestCase
{
    private const CHARSET = 'AbcDE123IJKLMN67QRSTUVWXYZaBCdefghijklmn123opq45rs67tuv89wxyz0FGH45OP89';

    public function testGeneratesDefaultFourCharacters(): void
    {
        $salt = SaltGenerator::generate();
        $this->assertSame(4, strlen($salt));
    }

    public function testGeneratesRequestedLength(): void
    {
        $this->assertSame(8, strlen(SaltGenerator::generate(8)));
        $this->assertSame(16, strlen(SaltGenerator::generate(16)));
        $this->assertSame(1, strlen(SaltGenerator::generate(1)));
    }

    public function testOnlyUsesValidCharsetCharacters(): void
    {
        // Generate a large sample to catch any stray characters
        $salt = SaltGenerator::generate(200);

        $this->assertSame(200, strlen($salt));

        for ($i = 0; $i < strlen($salt); $i++) {
            $char = $salt[$i];
            $this->assertNotFalse(
                strpos(self::CHARSET, $char),
                sprintf('Character "%s" at position %d is not in the allowed charset.', $char, $i)
            );
        }
    }

    public function testGeneratesDifferentValues(): void
    {
        // Statistical: probability of collision with 4 chars from 72-char charset
        // is tiny. Run several times to be confident.
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = SaltGenerator::generate();
        }

        // At least two of the 20 results should differ
        $unique = array_unique($results);
        $this->assertGreaterThan(1, count($unique), 'SaltGenerator appears to return the same value every time.');
    }

    public function testReturnsString(): void
    {
        $this->assertIsString(SaltGenerator::generate());
    }
}
