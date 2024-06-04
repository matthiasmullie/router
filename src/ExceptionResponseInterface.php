<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Message\ResponseInterface;

interface ExceptionResponseInterface
{
    public function handle(\Exception $exception): ResponseInterface;
}
