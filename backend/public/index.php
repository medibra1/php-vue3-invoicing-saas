<?php

declare(strict_types=1);

use App\Core\Container\Container;
use App\Core\Database\Connection;
use App\Core\Http\CorsMiddleware;
use App\Core\Http\JsonBodyParserMiddleware;
use App\Core\Kernel;
use App\Core\Router\Router;
use App\Modules\Auth\JwtDecoder;
use App\Modules\Auth\JwtEncoder;

require dirname(__DIR__) . '/vendor/autoload.php';

loadEnvFile(dirname(__DIR__) . '/.env');

$container = new Container();
$container->singleton(Connection::class, static fn (): Connection => Connection::fromEnv());
$container->singleton(JwtEncoder::class, static fn (): JwtEncoder => JwtEncoder::fromEnv());
$container->singleton(JwtDecoder::class, static fn (): JwtDecoder => JwtDecoder::fromEnv());
$container->singleton(CorsMiddleware::class, static fn (): CorsMiddleware => CorsMiddleware::fromEnv());

$router = new Router();

// Routes are registered module by module as each is built (see the
// roadmap in CLAUDE.md), starting with the Auth module in Phase 1, e.g.:
//
// $router->group(['prefix' => '/api/v1'], function (Router $router): void {
//     $router->post('/auth/login', [AuthController::class, 'login']); // public, no middleware
//
//     $router->group(['middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]], function (Router $router): void {
//         $router->post('/auth/logout', [AuthController::class, 'logout']);
//
//         $router->get('/invoices', [InvoiceController::class, 'index'], [
//             [PermissionMiddleware::class, 'invoices.view'],
//         ]);
//     });
// });

$kernel = new Kernel($router, $container, globalMiddlewares: [
    CorsMiddleware::class,
    JsonBodyParserMiddleware::class,
    // RateLimitMiddleware is added here once implemented.
    // AuthMiddleware/TenantResolver/Permission stay route-group-scoped,
    // never global — public endpoints like /auth/login must stay
    // reachable without a token.
]);

$kernel->run();
