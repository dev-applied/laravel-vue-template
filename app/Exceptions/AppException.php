<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AppException extends Exception implements HttpExceptionInterface
{
    public function __construct(string $message = 'Server Error', int $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function getHeaders(): array
    {
        return [];
    }
}
