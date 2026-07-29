<?php

declare(strict_types=1);

namespace App\Core\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the uniform `{"error": {"status", "message", ...}}` JSON shape
 * used everywhere a request is rejected: Kernel (404/405/500) and any
 * middleware that short-circuits the pipeline (401 from AuthMiddleware,
 * 403 from TenantResolverMiddleware/PermissionMiddleware...). Keeping
 * this in one place means every error response looks the same to API
 * consumers regardless of which layer produced it.
 */
final class JsonErrorResponse
{
    private static ?Psr17Factory $factory = null;

    /** @param array<string, mixed> $extra Additional safe-to-expose fields (e.g. 'allowed' on a 405) */
    public static function build(int $status, string $message, array $extra = []): ResponseInterface
    {
        self::$factory ??= new Psr17Factory();

        $response = self::$factory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write((string) json_encode(
            ['error' => ['status' => $status, 'message' => $message, ...$extra]],
            JSON_UNESCAPED_SLASHES
        ));

        return $response;
    }
}
