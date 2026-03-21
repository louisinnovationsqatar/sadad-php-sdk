<?php
namespace LouisInnovations\Sadad\Exceptions;

class AuthenticationException extends SadadException
{
    public function __construct(
        string $message = 'Authentication failed',
        ?int $httpStatus = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'AUTH_FAILED', $httpStatus, $previous);
    }
}
