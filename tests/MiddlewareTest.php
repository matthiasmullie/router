<?php

declare(strict_types=1);

namespace MatthiasMullie\Router\Tests;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use MatthiasMullie\Router\RequestMethods;
use MatthiasMullie\Router\Router;
use Monolog;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class MiddlewareTest extends TestCase
{
    protected LoggerInterface $logger;
    protected Router $router;

    public function setUp(): void
    {
        $this->logger = new Monolog\Logger('test');
        $this->logger->pushHandler(new Monolog\Handler\TestHandler());
        $this->router = new Router(
            logger: $this->logger,
        );

        $this->router->addRoute(
            RequestMethods::GET,
            '/without-route-middleware/before',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                }
            },
        );

        $this->router->addRoute(
            RequestMethods::GET,
            '/with-route-middleware/before',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                }
            },
        )->addMiddleware(
            new class () implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    $request = $request->withAttribute('middleware', $request->getAttribute('middleware', '') . ';route-middleware-before,pre-handle');
                    $response = $handler->handle($request);

                    return $response->withBody(Utils::streamFor($response->getBody() . ';route-middleware-before,post-handle'));
                }
            },
        );

        $this->router->addGroup('/without-group-middleware')
            ->addRoute(
                RequestMethods::GET,
                '/before',
                new class () implements RequestHandlerInterface {
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                    }
                },
            );
        $this->router->addGroup('/with-group-middleware')
            ->addRoute(
                RequestMethods::GET,
                '/before',
                new class () implements RequestHandlerInterface {
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                    }
                },
            )->addMiddleware(
                new class () implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        $request = $request->withAttribute('middleware', $request->getAttribute('middleware', '') . ';group-middleware-before,pre-handle');
                        $response = $handler->handle($request);

                        return $response->withBody(Utils::streamFor($response->getBody() . ';group-middleware-before,post-handle'));
                    }
                },
            );

        $this->router->addMiddleware(
            new class () implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    $request = $request->withAttribute('middleware', $request->getAttribute('middleware', '') . ';all-middleware,pre-handle');
                    $response = $handler->handle($request);

                    return $response->withBody(Utils::streamFor($response->getBody() . ';all-middleware,post-handle'));
                }
            },
        );

        $this->router->addRoute(
            RequestMethods::GET,
            '/without-route-middleware/after',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                }
            },
        );

        $this->router->addRoute(
            RequestMethods::GET,
            '/with-route-middleware/after',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                }
            },
        )->addMiddleware(
            new class () implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    $request = $request->withAttribute('middleware', $request->getAttribute('middleware', '') . ';route-middleware-after,pre-handle');
                    $response = $handler->handle($request);

                    return $response->withBody(Utils::streamFor($response->getBody() . ';route-middleware-after,post-handle'));
                }
            },
        );

        $this->router->addGroup('/without-group-middleware')
            ->addRoute(
                RequestMethods::GET,
                '/after',
                new class () implements RequestHandlerInterface {
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                    }
                },
            );
        $this->router->addGroup('/with-group-middleware')
            ->addRoute(
                RequestMethods::GET,
                '/after',
                new class () implements RequestHandlerInterface {
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return new Response(200, [], $request->getUri()->getPath() . $request->getAttribute('middleware'));
                    }
                },
            )->addMiddleware(
                new class () implements MiddlewareInterface {
                    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        $request = $request->withAttribute('middleware', $request->getAttribute('middleware', '') . ';group-middleware-after,pre-handle');
                        $response = $handler->handle($request);

                        return $response->withBody(Utils::streamFor($response->getBody() . ';group-middleware-after,post-handle'));
                    }
                },
            );

        parent::setUp();
    }

    public function testRouteWithoutMiddlewareBeforeGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/without-route-middleware/before'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/without-route-middleware/before;all-middleware,pre-handle;all-middleware,post-handle', (string) $response->getBody());
        $this->assertCount(4, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /without-route-middleware/before', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertSame('[Router] Response: GET /without-route-middleware/before: 200 OK', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
    }

    public function testRouteWithMiddlewareBeforeGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with-route-middleware/before'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with-route-middleware/before;route-middleware-before,pre-handle;all-middleware,pre-handle;all-middleware,post-handle;route-middleware-before,post-handle', (string) $response->getBody());
        $this->assertCount(5, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with-route-middleware/before', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
        $this->assertSame('[Router] Response: GET /with-route-middleware/before: 200 OK', $this->logger->getHandlers()[0]->getRecords()[4]['message']);
    }

    public function testGroupWithoutMiddlewareBeforeGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/without-group-middleware/before'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/without-group-middleware/before;all-middleware,pre-handle;all-middleware,post-handle', (string) $response->getBody());
        $this->assertCount(4, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /without-group-middleware/before', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertSame('[Router] Response: GET /without-group-middleware/before: 200 OK', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
    }

    public function testGroupWithMiddlewareBeforeGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with-group-middleware/before'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with-group-middleware/before;group-middleware-before,pre-handle;all-middleware,pre-handle;all-middleware,post-handle;group-middleware-before,post-handle', (string) $response->getBody());
        $this->assertCount(5, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with-group-middleware/before', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
        $this->assertSame('[Router] Response: GET /with-group-middleware/before: 200 OK', $this->logger->getHandlers()[0]->getRecords()[4]['message']);
    }

    public function testRouteWithoutMiddlewareAfterGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/without-route-middleware/after'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/without-route-middleware/after;all-middleware,pre-handle;all-middleware,post-handle', (string) $response->getBody());
        $this->assertCount(4, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /without-route-middleware/after', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertSame('[Router] Response: GET /without-route-middleware/after: 200 OK', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
    }

    public function testRouteWithMiddlewareAfterGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with-route-middleware/after'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with-route-middleware/after;all-middleware,pre-handle;route-middleware-after,pre-handle;route-middleware-after,post-handle;all-middleware,post-handle', (string) $response->getBody());
        $this->assertCount(5, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with-route-middleware/after', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
        $this->assertSame('[Router] Response: GET /with-route-middleware/after: 200 OK', $this->logger->getHandlers()[0]->getRecords()[4]['message']);
    }

    public function testGroupWithoutMiddlewareAfterGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/without-group-middleware/after'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/without-group-middleware/after;all-middleware,pre-handle;all-middleware,post-handle', (string) $response->getBody());
        $this->assertCount(4, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /without-group-middleware/after', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertSame('[Router] Response: GET /without-group-middleware/after: 200 OK', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
    }

    public function testGroupWithMiddlewareAfterGenericMiddleware(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with-group-middleware/after'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with-group-middleware/after;all-middleware,pre-handle;group-middleware-after,pre-handle;group-middleware-after,post-handle;all-middleware,post-handle', (string) $response->getBody());
        $this->assertCount(5, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with-group-middleware/after', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertStringStartsWith('[Router] Executing middleware: Psr\Http\Server\MiddlewareInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
        $this->assertSame('[Router] Response: GET /with-group-middleware/after: 200 OK', $this->logger->getHandlers()[0]->getRecords()[4]['message']);
    }
}
