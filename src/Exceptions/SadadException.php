<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Exceptions;

use RuntimeException;

class SadadException extends RuntimeException
{
    protected string $errorCode;
    protected ?int $httpStatus;

    public function __construct(
        string $message,
        string $errorCode = 'SADAD_ERROR',
        ?int $httpStatus = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
