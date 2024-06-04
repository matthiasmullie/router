<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Message\ResponseInterface;

class ExceptionResponse implements ExceptionResponseInterface
{
    public function __construct(readonly private ResponseInterface $response) {}

    public function handle(\Exception $exception): ResponseInterface
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

        return $response;
    }
}
