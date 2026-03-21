<?php
namespace LouisInnovations\Sadad\Exceptions;

class RefundException extends SadadException
{
    public function __construct(
        string $message,
        string $errorCode = 'REFUND_ERROR',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, null, $previous);
    }
}
