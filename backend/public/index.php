<?php

declare(strict_types=1);

use App\Core\Container\Container;
use App\Core\Database\Connection;
use App\Core\Http\CorsMiddleware;
use App\Core\Http\JsonBodyParserMiddleware;
use App\Core\Kernel;
use App\Core\Router\Router;
use App\Modules\Auth\AuthController;
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

$router->group(['prefix' => '/api/v1'], function (Router $router): void {
    // No AuthMiddleware on any of these: register/login issue the first
    // token, and refresh/logout are identified by the refresh token
    // itself, not a Bearer access token.
    $router->group(['prefix' => '/auth'], function (Router $router): void {
        $router->post('/register', [AuthController::class, 'register']);
        $router->post('/login', [AuthController::class, 'login']);
        $router->post('/refresh', [AuthController::class, 'refresh']);
        $router->post('/logout', [AuthController::class, 'logout']);
    });

    // Protected routes will follow this pattern once each module exists:
    // $router->group(['middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]], function (Router $router): void {
    //     $router->get('/invoices', [InvoiceController::class, 'index'], [
    //         [PermissionMiddleware::class, 'invoices.view'],
    //     ]);
    // });
});

$kernel = new Kernel($router, $container, globalMiddlewares: [
    CorsMiddleware::class,
    JsonBodyParserMiddleware::class,
    // RateLimitMiddleware is added here once implemented.
    // AuthMiddleware/TenantResolver/Permission stay route-group-scoped,
    // never global — public endpoints like /auth/login must stay
    // reachable without a token.
]);

$kernel->run();
