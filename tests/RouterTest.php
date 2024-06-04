<?php

declare(strict_types=1);

namespace MatthiasMullie\Router\Tests;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use MatthiasMullie\Router\Exception;
use MatthiasMullie\Router\RequestMethods;
use MatthiasMullie\Router\Router;
use Monolog;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RouterTest extends TestCase
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
            '/test',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath());
                }
            },
        );
        $this->router->addRoute(
            RequestMethods::GET,
            '/with/{variable}',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath());
                }
            },
        );
        $this->router->addRoute(
            RequestMethods::GET,
            '/exception',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new Exception('Not Implemented', 501);
                }
            },
        );

        parent::setUp();
    }

    public function testNoRoutes(): void
    {
        $logger = new Monolog\Logger('test');
        $logger->pushHandler(new Monolog\Handler\TestHandler());
        $router = new Router(
            logger: $logger,
        );

        try {
            $router->handle(new ServerRequest('GET', '/test-invalid-route'));
        } catch (Exception $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Not Found', $e->getMessage());
            $this->assertCount(1, $logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: GET /test-invalid-route', $logger->getHandlers()[0]->getRecords()[0]['message']);
        }
    }

    public function testOk(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/test'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/test', (string) $response->getBody());
        $this->assertCount(3, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /test', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /test: 200 OK', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
    }

    public function testNotFound(): void
    {
        try {
            $this->router->handle(new ServerRequest('GET', '/test-invalid-route'));
        } catch (Exception $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Not Found', $e->getMessage());
            $this->assertCount(1, $this->logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: GET /test-invalid-route', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        }
    }

    public function testMethodNotAllowed(): void
    {
        try {
            $this->router->handle(new ServerRequest('POST', '/test'));
        } catch (Exception $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertSame('Method Not Allowed', $e->getMessage());
            $this->assertSame(['Allow' => 'GET'], $e->getHeaders());
            $this->assertCount(1, $this->logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: POST /test', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        }
    }

    public function testExceptionThrown(): void
    {
        try {
            $this->router->handle(new ServerRequest('GET', '/exception'));
        } catch (Exception $e) {
            $this->assertSame(501, $e->getStatusCode());
            $this->assertSame('Not Implemented', $e->getMessage());
            $this->assertCount(2, $this->logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: GET /exception', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
            $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        }
    }

    public function testPlaceholderRoute(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with/variable'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with/variable', (string) $response->getBody());
        $this->assertCount(3, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with/variable', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /with/variable: 200 OK', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
    }
}
