<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

class Exception extends \Exception
{
    private StatusCodes $statusCode;

    public function __construct(
        string $message = 'Internal Server Error',
        StatusCodes|int $statusCode = StatusCodes::InternalServerError,
        readonly private array $headers = [],
        int $code = 0,
        ?\Exception $previous = null,
    ) {
        $this->statusCode = $statusCode instanceof StatusCodes ? $statusCode : StatusCodes::from($statusCode);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode->value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
