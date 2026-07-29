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
