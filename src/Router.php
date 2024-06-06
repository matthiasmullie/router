<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use FastRoute;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;

class Router extends Group implements RequestHandlerInterface
{
    private FastRoute\Dispatcher\GroupCountBased $dispatcher;

    private int $routeCount = 0;

    public function __construct(
        private ?ExceptionResponseInterface $exceptionResponse = null,
        readonly private LoggerInterface $logger = new NullLogger(),
    ) {
        // default to not handling exceptions, but throwing them again
        if (!isset($this->exceptionResponse)) {
            $this->exceptionResponse = new class () implements ExceptionResponseInterface {
                public function handle(\Exception $exception): ResponseInterface
                {
                    throw $exception;
                }
            };
        }

        parent::__construct('', $logger);
    }

    /**
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->logger instanceof NullLogger) {
            $this->logger->debug(
                "[Router] Request: {$request->getMethod()} {$request->getUri()}",
                [
                    'protocol_version' => $request->getProtocolVersion(),
                    'headers' => $request->getHeaders(),
                    'body' => (string) $request->getBody(),
                ],
            );
        }

        $routes = $this->getRoutes();
        if (!isset($this->dispatcher) || $this->routeCount !== count($routes)) {
            // build FastRoute collector if it hasn't already been built,
            // or routes have changed since it was last built
            $this->buildDispatcher($routes);
            $this->routeCount = count($routes);
        }

        try {
            $response = $this->dispatch($request);
        } catch (\Exception $exception) {
            $response = $this->exceptionResponse->handle($exception);
        }

        if (!$this->logger instanceof NullLogger) {
            $this->logger->debug(
                "[Router] Response: {$request->getMethod()} {$request->getUri()}: {$response->getStatusCode()} {$response->getReasonPhrase()}",
                [
                    'protocol_version' => $response->getProtocolVersion(),
                    'headers' => $response->getHeaders(),
                    'body' => (string) $response->getBody(),
                ],
            );
        }

        return $response;
    }

    public function output(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            header(sprintf('%s: %s', $name, $response->getHeaderLine($name)), false);
        }
        echo $response->getBody();
    }

    /**
     * @param Route[] $routes
     */
    private function buildDispatcher(array $routes): void
    {
        $collector = new FastRoute\RouteCollector(
            new FastRoute\RouteParser\Std(),
            new FastRoute\DataGenerator\GroupCountBased(),
        );
        foreach ($routes as $route) {
            $collector->addRoute(
                $route->getMethod()->value,
                $route->getPath(),
                $route,
            );
        }
        $this->dispatcher = new FastRoute\Dispatcher\GroupCountBased($collector->getData());
    }

    /**
     * @throws Exception
     */
    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath(),
        );

        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::NOT_FOUND:
                throw new Exception(
                    'Not Found',
                    StatusCodes::NotFound,
                );
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowed = (array) $routeInfo[1];
                throw new Exception(
                    'Method Not Allowed',
                    StatusCodes::MethodNotAllowed,
                    ['Allow' => implode(', ', array_unique($allowed))],
                );
            case FastRoute\Dispatcher::FOUND:
                [, $handler, $variables] = $routeInfo;

                return $handler->handle($request->withAttribute('variables', $variables));
        }

        throw new Exception(
            'Internal Server Error',
            StatusCodes::InternalServerError,
        );
    }
}
