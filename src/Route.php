<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Route extends MiddlewareContainer implements RequestHandlerInterface
{
    public function __construct(
        readonly private RequestMethods $method,
        readonly private string $path,
        readonly private RequestHandlerInterface $handler,
        readonly private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getMethod(): RequestMethods
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->getMiddleware();
        if ($middleware) {
            // if we have more middleware to process, create a new handler
            // with the remaining middleware, from which the next one will
            // then be processed when calling ->handle, and so on, until
            // we're finally left with only the actual handler
            $handler = new self(
                $this->method,
                $this->path,
                $this->handler,
                $this->logger,
            );

            $rest = array_slice($middleware, 1);
            foreach ($rest as $m) {
                $handler->addMiddleware($m);
            }

            $middlewareClass = $middleware[0]::class;
            $this->logger->debug("[Router] Executing middleware: {$middlewareClass}");

            return $middleware[0]->process($request, $handler);
        }

        $handlerClass = $this->handler::class;
        $this->logger->debug("[Router] Executing handler: {$handlerClass}");

        return $this->handler->handle($request);
    }
}
