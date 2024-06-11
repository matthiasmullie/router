<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ExceptionResponse implements ExceptionResponseInterface
{
    public function __construct(
        readonly private ResponseInterface $response,
        readonly private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function handle(\Exception $exception, ServerRequestInterface $request): ResponseInterface
    {
        if (!$exception instanceof Exception) {
            $exception = new Exception(
                $exception->getMessage(),
                StatusCodes::InternalServerError,
                [],
                $exception->getCode(),
                $exception,
            );
        }

        $response = $this->response;
        $response = $response->withStatus($exception->getStatusCode(), $exception->getMessage());
        foreach ($exception->getHeaders() as $header => $value) {
            $response = $response->withAddedHeader($header, $value);
        }

        $this->logger->warning(
            $exception->getMessage(),
            [
                'status_code' => $exception->getStatusCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ],
        );

        return $response;
    }
}
