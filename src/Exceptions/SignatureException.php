<?php
namespace LouisInnovations\Sadad\Exceptions;

class SignatureException extends SadadException
{
    protected string $expectedHash;
    protected string $receivedHash;

    public function __construct(
        string $expectedHash,
        string $receivedHash,
        string $message = 'Signature verification failed'
    ) {
        parent::__construct($message, 'SIGNATURE_MISMATCH');
        $this->expectedHash = $expectedHash;
        $this->receivedHash = $receivedHash;
    }

    public function getExpectedHash(): string
    {
        return $this->expectedHash;
    }

    public function getReceivedHash(): string
    {
        return $this->receivedHash;
    }
}
