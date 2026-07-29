<?php

declare(strict_types=1);

namespace App\Core\Router;

/**
 * No route matches the requested URI -> should be translated into a 404
 * by the Kernel's global error handler.
 */
final class RouteNotFoundException extends \RuntimeException
{
}

/**
 * The URI matches an existing route but not with this HTTP method
 * (e.g. DELETE /invoices/{id} while only GET/PUT exist) -> should be
 * translated into a 405 with the Allow header populated from
 * $allowedMethods.
 */
final class MethodNotAllowedException extends \RuntimeException
{
    /** @param string[] $allowedMethods */
    public function __construct(public readonly array $allowedMethods)
    {
        parent::__construct('Method not allowed for this route.');
    }
}
