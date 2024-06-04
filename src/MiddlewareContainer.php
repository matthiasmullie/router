<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

use Psr\Http\Server\MiddlewareInterface;

class MiddlewareContainer
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function addMiddleware(MiddlewareInterface $middleware): static
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * @return MiddlewareInterface[]
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
