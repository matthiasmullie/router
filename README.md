# Router

[![Build status](https://img.shields.io/github/actions/workflow/status/matthiasmullie/router/test.yml?branch=main&style=flat-square)](https://github.com/matthiasmullie/router/actions/workflows/test.yml)
[![Code coverage](https://img.shields.io/codecov/c/gh/matthiasmullie/router?style=flat-square)](https://codecov.io/gh/matthiasmullie/router)
[![Latest version](https://img.shields.io/packagist/v/matthiasmullie/router?style=flat-square)](https://packagist.org/packages/matthiasmullie/router)
[![Downloads total](https://img.shields.io/packagist/dt/matthiasmullie/router?style=flat-square)](https://packagist.org/packages/matthiasmullie/router)
[![License](https://img.shields.io/packagist/l/matthiasmullie/router?style=flat-square)](https://github.com/matthiasmullie/router/blob/main/LICENSE)


## Installation

Simply add a dependency on `matthiasmullie/router` to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require matthiasmullie/router
```


## Usage

### Routes

This router provides a simple way to map routes (request method + path) to request handlers.

Request handlers are classes that implement [PSR-15](https://www.php-fig.org/psr/psr-15/)'s the `Psr\Http\Server\RequestHandlerInterface` interface,
which accept a `Psr\Http\Message\ServerRequestInterface` and return a `Psr\Http\Message\ResponseInterface` object.

This is essentially just a simple PSR-15 layer on top of [FastRoute](https://packagist.org/packages/nikic/fast-route).

It's about as simple as that:

```php
use MatthiasMullie\Router\RequestMethods;
use MatthiasMullie\Router\Router;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

// create 2 example request handlers
class RouteOne implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['message' => 'Hello world']),
        );
    }
}
class RouteTwo implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
            '<html><body><p>Hello world</p></body></html>',
        );
    }
}

// create router instance and add both routes
$this->router = new Router();
$this->router->addRoute(
    RequestMethods::GET,
    '/one',
    new RouteOne(),
);
$this->router->addRoute(
    RequestMethods::GET,
    '/two',
    new RouteTwo(),
);

// route a request, receive response from the matching request handler
$response = $router->handle(
    new ServerRequest('GET', '/one'),
);
echo $response->getBody(); // outputs: {"message":"Hello world"}
```


### Groups

For convenient, routes with the same prefix can be bundled together in a group.

Like so:

```php
// create router instance and add an individual route
$this->router = new Router();
$this->router->addRoute(
    RequestMethods::GET,
    '/one', // maps to /one
    new RouteOne(),
);

// then 2 more routes with a shared prefix 
$group = $this->router->addGroup('/prefix');
$group->addRoute(
    RequestMethods::GET,
    '/two', // maps to /prefix/two
    new RouteTwo(),
);
$group->addRoute(
    RequestMethods::GET,
    '/three', // maps to /prefix/three
    new RouteThree(),
);
```


### Middleware

In addition to PSR-15's request handlers, this router also supports PSR-15's `Psr\Http\Server\MiddlewareInterface`,
which wrap around a request handler, executing either or both before and after handling the request.

This simplifies logic that is shared between multiple request handlers, like authentication, logging, etc.

Middleware can be added to individual routes, all routes within a group, or simply all routes.

Like this:

```php
class Middleware implements MiddlewareInterface {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // execute something before handling the request,
        // or manipulate the request object
        $request = $request->withAttribute('middleware', 'Hello world');

        // invoke the handler (or the next middleware, if any)
        // and retrieve the response
        $response = $handler->handle($request);

        // execute something after handling the request,
        // or manipulate the response object
        return $response->withAddedHeader('Content-Type', 'application/json; charset=utf-8);
    }
} 

// create router instance and add a route with middleware
$this->router = new Router();
$this->router
    ->addRoute(
        RequestMethods::GET,
        '/one',
        new RouteOne(),
    )
    ->addMiddleware(
        new Middleware(),
    );

// or add a group with middleware that applies to all routes within that group
$this->router
    ->addGroup('/prefix')
    ->addMiddleware(
        new Middleware(),
    )
    ->addRoute(
        RequestMethods::GET,
        '/two',
        new RouteTwo(),
    );

// or add middleware that applies to all routes
$this->router->addMiddleware(
    new Middleware(),
);
```


### Exceptions

By default, any exception that is encountered, either within the request handler/middleware, or as part of routing (e.g. invalid route),
will simply be thrown. It is, however, possible to add a custom exception handler, which will catch any exception and return a response.

This can be done by supplying an `MatthiasMullie\Router\ExceptionResponseInterface` instance to the router, e.g. the provided `MatthiasMullie\Router\ExceptionResponse`.
This will catch any exception and return a response with the appropriate status code.

This package also comes with a custom `MatthiasMullie\Router\Exception` class that allows including HTTP status codes and headers in the exception.

Example:

```php
use MatthiasMullie\Router\Exception;
use MatthiasMullie\Router\ExceptionResponse;

class RouteException implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new Exception('Not Implemented', 501);
    }
}

$this->router = new Router(new ExceptionResponse(new Response()));
$this->router->addRoute(
    RequestMethods::GET,
    '/exception',
    new RouteException(),
);

$response = $router->handle(
    new ServerRequest('GET', '/exception'),
);
// $response now includes the 501 Not Implemented status code & reason phrase
```


## License

router is [MIT](http://opensource.org/licenses/MIT) licensed.
