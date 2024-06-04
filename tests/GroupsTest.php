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

class GroupsTest extends TestCase
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
        $group = $this->router->addGroup('/with');
        $group->addRoute(
            RequestMethods::GET,
            '/static',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath());
                }
            },
        );
        $group->addRoute(
            RequestMethods::GET,
            '/exception',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new Exception('Not Implemented', 501);
                }
            },
        );
        $group->addRoute(
            RequestMethods::GET,
            '/{variable}',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath());
                }
            },
        );
        $subgroup = $group->addGroup('/{variable}');
        $subgroup->addRoute(
            RequestMethods::GET,
            '/nested',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], $request->getUri()->getPath());
                }
            },
        );

        parent::setUp();
    }

    public function testSingleRouteOk(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/test'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/test', (string) $response->getBody());
        $this->assertCount(3, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /test', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /test: 200 OK', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
    }

    public function testOk(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with/static'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with/static', (string) $response->getBody());
        $this->assertCount(3, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with/static', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /with/static: 200 OK', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
    }

    public function testNotFound(): void
    {
        try {
            $this->router->handle(new ServerRequest('GET', '/with/test/invalid/route'));
        } catch (Exception $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Not Found', $e->getMessage());
            $this->assertCount(1, $this->logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: GET /with/test/invalid/route', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        }
    }

    public function testMethodNotAllowed(): void
    {
        try {
            $this->router->handle(new ServerRequest('POST', '/with/static'));
        } catch (Exception $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertSame('Method Not Allowed', $e->getMessage());
            $this->assertSame(['Allow' => 'GET'], $e->getHeaders());
            $this->assertCount(1, $this->logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: POST /with/static', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        }
    }

    public function testExceptionThrown(): void
    {
        try {
            $this->router->handle(new ServerRequest('GET', '/with/exception'));
        } catch (Exception $e) {
            $this->assertSame(501, $e->getStatusCode());
            $this->assertSame('Not Implemented', $e->getMessage());
            $this->assertCount(2, $this->logger->getHandlers()[0]->getRecords());
            $this->assertSame('[Router] Request: GET /with/exception', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
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

    public function testNestedGroups(): void
    {
        $response = $this->router->handle(new ServerRequest('GET', '/with/groups/nested'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with/groups/nested', (string) $response->getBody());
        $this->assertCount(3, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with/groups/nested', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /with/groups/nested: 200 OK', $this->logger->getHandlers()[0]->getRecords()[2]['message']);
    }

    public function testCombineGroupDeclarations(): void
    {
        $logger = new Monolog\Logger('test');
        $logger->pushHandler(new Monolog\Handler\TestHandler());
        $router = new Router(
            logger: $logger,
        );

        $router->addGroup('/group')->addRoute(
            RequestMethods::GET,
            '/static',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], '/test');
                }
            },
        );
        $router->addGroup('/group')->addRoute(
            RequestMethods::GET,
            '/{variable}',
            new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], "{$request->getUri()->getPath()}");
                }
            },
        );

        // test that first route is available, and the duplicate group declaration
        // didn't wipe this one out
        $response = $this->router->handle(new ServerRequest('GET', '/with/static'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with/static', (string) $response->getBody());
        $this->assertCount(3, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with/static', $this->logger->getHandlers()[0]->getRecords()[0]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /with/static: 200 OK', $this->logger->getHandlers()[0]->getRecords()[2]['message']);

        // test that first route is available, and the duplicate group declaration
        // wasn't prevented, but added to the existing one
        $response = $this->router->handle(new ServerRequest('GET', '/with/variable'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/with/variable', (string) $response->getBody());
        $this->assertCount(6, $this->logger->getHandlers()[0]->getRecords());
        $this->assertSame('[Router] Request: GET /with/variable', $this->logger->getHandlers()[0]->getRecords()[3]['message']);
        $this->assertStringStartsWith('[Router] Executing handler: Psr\Http\Server\RequestHandlerInterface@anonymous', $this->logger->getHandlers()[0]->getRecords()[1]['message']);
        $this->assertSame('[Router] Response: GET /with/variable: 200 OK', $this->logger->getHandlers()[0]->getRecords()[5]['message']);
    }
}
