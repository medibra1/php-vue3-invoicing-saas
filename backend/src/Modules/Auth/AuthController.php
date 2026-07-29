<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Http\JsonErrorResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Thin HTTP layer over AuthService: pulls fields out of the parsed JSON
 * body (JsonBodyParserMiddleware already ran), delegates, and turns the
 * result — or an AuthException — into a response. No business logic
 * lives here; see AuthService for that.
 */
final class AuthController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly AuthService $authService)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Sign up a new tenant with its first user, who becomes owner',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tenantName', 'name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'tenantName', type: 'string', example: 'Acme Freelance'),
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@acme.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Account created — accessToken/refreshToken/user'),
            new OA\Response(response: 409, description: 'Email already registered'),
            new OA\Response(response: 422, description: 'Invalid input'),
        ]
    )]
    public function register(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $body = $this->body($request);

            $result = $this->authService->register(
                (string) ($body['tenantName'] ?? ''),
                (string) ($body['name'] ?? ''),
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? '')
            );

            return $this->json(201, $result);
        });
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Exchange email + password for an access + refresh token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated — accessToken/refreshToken/user'),
            new OA\Response(response: 401, description: 'Invalid email or password'),
            new OA\Response(response: 403, description: 'Account/tenant inactive'),
        ]
    )]
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $body = $this->body($request);

            $result = $this->authService->login(
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? '')
            );

            return $this->json(200, $result);
        });
    }

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        summary: 'Rotate a refresh token for a new access + refresh token pair',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refreshToken'],
                properties: [new OA\Property(property: 'refreshToken', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rotated — new accessToken/refreshToken/user'),
            new OA\Response(response: 401, description: 'Invalid, expired, or already-used refresh token'),
            new OA\Response(response: 403, description: 'Account/tenant inactive'),
        ]
    )]
    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $body = $this->body($request);

            $result = $this->authService->refresh((string) ($body['refreshToken'] ?? ''));

            return $this->json(200, $result);
        });
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Revoke a refresh token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['refreshToken'],
                properties: [new OA\Property(property: 'refreshToken', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Revoked — same response whether or not the token was valid'),
        ]
    )]
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $body = $this->body($request);

            $this->authService->logout((string) ($body['refreshToken'] ?? ''));

            return $this->psr17Factory->createResponse(204);
        });
    }

    /** Maps AuthException -> the JSON error shape every other layer already uses. */
    private function respond(\Closure $action): ResponseInterface
    {
        try {
            return $action();
        } catch (AuthException $e) {
            return JsonErrorResponse::build($e->status, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): ResponseInterface
    {
        $response = $this->psr17Factory->createResponse($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
