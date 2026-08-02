<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container\Container;
use App\Core\Http\CorsMiddleware;
use App\Core\Kernel;
use App\Core\Router\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Regression test: a controller (or per-route middleware) throwing must
 * still produce a CORS-headered response, not just a bare 500 — the 500
 * catch lives *inside* the global middleware stack (see Kernel::handle()'s
 * class doc) specifically so CorsMiddleware still gets to run on the way
 * back out. This was discovered for real: a missing migration made an
 * insert throw mid-request, and the resulting 500 had no CORS headers,
 * which the browser reported as a CORS failure instead of the real error.
 */
final class KernelCorsOnErrorTest extends TestCase
{
    public function testAnUncaughtExceptionInAControllerStillCarriesCorsHeaders(): void
    {
        $container = new Container();
        $router = new Router();
        $router->get('/boom', [ExplodingController::class, 'handle']);

        $kernel = new Kernel($router, $container, globalMiddlewares: [CorsMiddleware::class]);
        $container->instance(
            CorsMiddleware::class,
            new CorsMiddleware(['http://localhost:5173'])
        );

        $request = new ServerRequest('GET', '/boom', ['Origin' => 'http://localhost:5173']);

        $response = $kernel->handle($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('http://localhost:5173', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}

final class ExplodingController
{
    public function handle(): never
    {
        throw new \RuntimeException('simulated failure deep in the stack');
    }
}
