<?php

declare(strict_types=1);

use App\Core\Container\Container;
use App\Core\Database\Connection;
use App\Core\Http\CorsMiddleware;
use App\Core\Http\JsonBodyParserMiddleware;
use App\Core\Kernel;
use App\Core\Router\Router;
use App\Modules\Auth\AuthController;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\JwtDecoder;
use App\Modules\Auth\JwtEncoder;
use App\Modules\Auth\PermissionMiddleware;
use App\Modules\Client\ClientController;
use App\Modules\Invoice\InvoiceController;
use App\Modules\Payment\PaymentController;
use App\Modules\Profile\AvatarService;
use App\Modules\Profile\ProfileController;
use App\Modules\Quote\QuoteController;
use App\Modules\Stats\StatsController;
use App\Modules\Tenant\TenantResolverMiddleware;

// PHP's built-in dev server (`php -S ... public/index.php`) routes
// *every* request through this router script, including ones for real
// static files (e.g. Modules\Profile's uploaded avatars) — unlike
// Apache/Nginx, it never serves an existing file directly unless the
// router explicitly opts out by returning false. Production deploys
// behind a real webserver don't need this (its own docroot handling
// already serves public/uploads/* directly), so this is dev-only.
if (PHP_SAPI === 'cli-server') {
    $requestedFile = realpath(__DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

    if ($requestedFile !== false && is_file($requestedFile) && str_starts_with($requestedFile, __DIR__)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

loadEnvFile(dirname(__DIR__) . '/.env');

$container = new Container();
$container->singleton(Connection::class, static fn (): Connection => Connection::fromEnv());
$container->singleton(JwtEncoder::class, static fn (): JwtEncoder => JwtEncoder::fromEnv());
$container->singleton(JwtDecoder::class, static fn (): JwtDecoder => JwtDecoder::fromEnv());
$container->singleton(CorsMiddleware::class, static fn (): CorsMiddleware => CorsMiddleware::fromEnv());
$container->singleton(AvatarService::class, static fn (): AvatarService => new AvatarService(
    dirname(__DIR__) . '/public/uploads/avatars',
    rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/uploads/avatars'
));

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

    $router->group(
        ['prefix' => '/clients', 'middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]],
        function (Router $router): void {
            $router->get('', [ClientController::class, 'index'], [[PermissionMiddleware::class, 'clients.view']]);
            $router->get('/{id}', [ClientController::class, 'show'], [[PermissionMiddleware::class, 'clients.view']]);
            $router->post('', [ClientController::class, 'store'], [[PermissionMiddleware::class, 'clients.create']]);
            $router->put('/{id}', [ClientController::class, 'update'], [[PermissionMiddleware::class, 'clients.update']]);
            $router->delete('/{id}', [ClientController::class, 'destroy'], [[PermissionMiddleware::class, 'clients.delete']]);
        }
    );

    $router->group(
        ['prefix' => '/quotes', 'middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]],
        function (Router $router): void {
            $router->get('', [QuoteController::class, 'index'], [[PermissionMiddleware::class, 'quotes.view']]);
            $router->get('/{id}', [QuoteController::class, 'show'], [[PermissionMiddleware::class, 'quotes.view']]);
            $router->post('', [QuoteController::class, 'store'], [[PermissionMiddleware::class, 'quotes.create']]);
            $router->put('/{id}', [QuoteController::class, 'update'], [[PermissionMiddleware::class, 'quotes.update']]);
            $router->delete('/{id}', [QuoteController::class, 'destroy'], [[PermissionMiddleware::class, 'quotes.delete']]);
            $router->post('/{id}/status', [QuoteController::class, 'updateStatus'], [[PermissionMiddleware::class, 'quotes.update']]);
            $router->post('/{id}/convert', [QuoteController::class, 'convert'], [[PermissionMiddleware::class, 'quotes.convert']]);
        }
    );

    $router->group(
        ['prefix' => '/invoices', 'middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]],
        function (Router $router): void {
            $router->get('', [InvoiceController::class, 'index'], [[PermissionMiddleware::class, 'invoices.view']]);
            $router->get('/{id}', [InvoiceController::class, 'show'], [[PermissionMiddleware::class, 'invoices.view']]);
            $router->get('/{id}/pdf', [InvoiceController::class, 'pdf'], [[PermissionMiddleware::class, 'invoices.view']]);
            $router->post('', [InvoiceController::class, 'store'], [[PermissionMiddleware::class, 'invoices.create']]);
            $router->put('/{id}', [InvoiceController::class, 'update'], [[PermissionMiddleware::class, 'invoices.update']]);
            $router->delete('/{id}', [InvoiceController::class, 'destroy'], [[PermissionMiddleware::class, 'invoices.delete']]);
            $router->post('/{id}/status', [InvoiceController::class, 'updateStatus'], [[PermissionMiddleware::class, 'invoices.update']]);
            $router->get('/{id}/payments', [PaymentController::class, 'index'], [[PermissionMiddleware::class, 'payments.view']]);
            $router->post('/{id}/payments', [PaymentController::class, 'store'], [[PermissionMiddleware::class, 'payments.create']]);
        }
    );

    $router->group(
        ['prefix' => '/stats', 'middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]],
        function (Router $router): void {
            $router->get('/dashboard', [StatsController::class, 'dashboard'], [[PermissionMiddleware::class, 'stats.view']]);
        }
    );

    // No PermissionMiddleware on any of these: managing your own profile
    // is implicit for any authenticated user, not gated by the RBAC
    // permission matrix the way business-data endpoints are.
    $router->group(
        ['prefix' => '/me', 'middleware' => [AuthMiddleware::class, TenantResolverMiddleware::class]],
        function (Router $router): void {
            $router->get('', [ProfileController::class, 'show']);
            $router->put('', [ProfileController::class, 'update']);
            $router->post('/avatar', [ProfileController::class, 'uploadAvatar']);
            $router->delete('/avatar', [ProfileController::class, 'deleteAvatar']);
            $router->put('/password', [ProfileController::class, 'changePassword']);
        }
    );
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
