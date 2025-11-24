<?php

namespace Ngfw\DNSDumpster\Exceptions;

/**
 * Exception thrown when an API request fails.
 */
class ApiException extends DNSDumpsterException
{
    protected int $statusCode;

    /**
     * Create a new API exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $statusCode  The HTTP status code
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(string $message, int $statusCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
