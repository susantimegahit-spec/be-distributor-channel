<?php

namespace App\Exceptions;

use Exception;

class SapException extends Exception
{
    protected mixed $sapError;
    protected int $statusCode;

    public function __construct(string $message, mixed $sapError = null, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->sapError = $sapError;
        $this->statusCode = $statusCode;
    }

    public function getSapError(): mixed
    {
        return $this->sapError;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
