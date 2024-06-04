<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Group extends MiddlewareContainer
{
    /** @var Group[] */
    private array $groups = [];

    /** @var Route[] */
    private array $routes = [];

    public function __construct(
        readonly private string $prefix,
        readonly private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function addGroup(string $prefix): self
    {
        $groupPrefix = $this->prefix . $prefix;
        if (!isset($this->groups[$groupPrefix])) {
            $this->groups[$groupPrefix] = new self(
                $this->prefix . $prefix,
                $this->logger,
            );
        }

        return $this->groups[$groupPrefix];
    }

    public function addMiddleware(MiddlewareInterface $middleware): static
    {
        foreach ($this->groups as $group) {
            $group->addMiddleware($middleware);
        }
        foreach ($this->routes as $route) {
            $route->addMiddleware($middleware);
        }

        return parent::addMiddleware($middleware);
    }

    public function addRoute(
        RequestMethods|string $method,
        string $path,
        RequestHandlerInterface $handler,
    ): Route {
        $route = new Route(
            $method instanceof RequestMethods ? $method : RequestMethods::from($method),
            $this->prefix . $path,
            $handler,
            $this->logger,
        );
        foreach ($this->getMiddleware() as $middleware) {
            $route->addMiddleware($middleware);
        }
        $this->routes[] = $route;

        return $route;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        $routes = [];
        foreach ($this->groups as $group) {
            $routes = [...$routes, ...$group->getRoutes()];
        }
        foreach ($this->routes as $route) {
            $routes[] = $route;
        }

        return $routes;
    }
}
