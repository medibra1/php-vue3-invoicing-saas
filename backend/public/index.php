<?php

declare(strict_types=1);

use App\Core\Container\Container;
use App\Core\Database\Connection;
use App\Core\Kernel;
use App\Core\Router\Router;
use App\Modules\Auth\JwtDecoder;
use App\Modules\Auth\JwtEncoder;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
$container->singleton(Connection::class, static fn (): Connection => Connection::fromEnv());
$container->singleton(JwtEncoder::class, static fn (): JwtEncoder => JwtEncoder::fromEnv());
$container->singleton(JwtDecoder::class, static fn (): JwtDecoder => JwtDecoder::fromEnv());

$router = new Router();

// Routes are registered module by module as each is built (see the
// roadmap in CLAUDE.md), starting with the Auth module in Phase 1, e.g.:
//
// $router->group(['prefix' => '/api/v1'], function (Router $router): void {
//     $router->post('/auth/login', [AuthController::class, 'login']);
//     $router->group(['middleware' => [AuthMiddleware::class]], function (Router $router): void {
//         $router->post('/auth/logout', [AuthController::class, 'logout']);
//     });
// });

$kernel = new Kernel($router, $container, globalMiddlewares: [
    // Global pipeline (Cors, JsonBodyParser, RateLimit) is added here as
    // each middleware is implemented. AuthMiddleware/TenantResolver/
    // Permission stay route-group-scoped, never global — public
    // endpoints like /auth/login must stay reachable without a token.
]);

$kernel->run();
