<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ExceptionResponseInterface
{
    public function handle(\Exception $exception, ServerRequestInterface $request): ResponseInterface;
}
