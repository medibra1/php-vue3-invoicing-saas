<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Http\JsonErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Checks that the authenticated user holds a specific RBAC permission
 * before letting the request reach the controller. The permission is
 * declared per-route via a middleware tuple:
 *
 *   $router->get('/invoices', [InvoiceController::class, 'index'], [
 *       AuthMiddleware::class,
 *       TenantResolverMiddleware::class,
 *       [PermissionMiddleware::class, 'invoices.view'],
 *   ]);
 *
 * Must run after AuthMiddleware (needs `authClaims`) and
 * TenantResolverMiddleware (PermissionRepository needs CurrentTenant) —
 * see MiddlewarePipeline's class doc for the standard chain order.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    private readonly string $requiredPermission;

    /**
     * @param string $parameter The required permission slug, e.g.
     *        'invoices.create'. Named `$parameter` rather than
     *        `$requiredPermission`: MiddlewarePipeline injects a route's
     *        middleware tuple value into whichever constructor argument
     *        is literally called `$parameter` — see MiddlewarePipeline's
     *        class doc for the convention this relies on.
     */
    public function __construct(
        private readonly PermissionRepository $permissions,
        string $parameter
    ) {
        $this->requiredPermission = $parameter;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $claims = $request->getAttribute('authClaims');
        $userId = is_array($claims) ? ($claims['sub'] ?? null) : null;

        if ($userId === null) {
            return JsonErrorResponse::build(
                401,
                'Missing user context — is AuthMiddleware applied before PermissionMiddleware?'
            );
        }

        if (!$this->permissions->userHasPermission((int) $userId, $this->requiredPermission)) {
            return JsonErrorResponse::build(403, "Missing permission [{$this->requiredPermission}].");
        }

        return $handler->handle($request);
    }
}
