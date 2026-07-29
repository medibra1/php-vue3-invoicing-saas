<?php

declare(strict_types=1);

use App\Core\Container\Container;
use App\Core\Database\Connection;
use App\Core\Kernel;
use App\Core\Router\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
$container->singleton(Connection::class, static fn (): Connection => Connection::fromEnv());

$router = new Router();

// Routes are registered module by module as each is built (see the
// roadmap in CLAUDE.md), starting with the Auth module in Phase 1, e.g.:
//
// $router->group(['prefix' => '/api/v1'], function (Router $router): void {
//     $router->post('/auth/login', [AuthController::class, 'login']);
// });

$kernel = new Kernel($router, $container, globalMiddlewares: [
    // Global pipeline (Cors, JsonBodyParser, RateLimit, Auth,
    // TenantResolver, Permission) is added here as each middleware
    // is implemented.
]);

$kernel->run();
