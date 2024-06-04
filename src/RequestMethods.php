<?php

declare(strict_types=1);

namespace MatthiasMullie\Router;

/**
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods
 */
enum RequestMethods: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case CONNECT = 'CONNECT';
    case OPTIONS = 'OPTIONS';
    case TRACE = 'TRACE';
    case PATCH = 'PATCH';
}
