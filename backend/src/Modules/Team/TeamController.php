<?php

declare(strict_types=1);

namespace App\Modules\Team;

use App\Core\Http\JsonErrorResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TeamController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly TeamService $teamService)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/team',
        summary: 'List members of the current tenant, with their role',
        tags: ['Team'],
        responses: [new OA\Response(response: 200, description: 'Array of members')]
    )]
    public function index(): ResponseInterface
    {
        return $this->json(200, $this->teamService->list());
    }

    #[OA\Post(
        path: '/api/v1/team',
        summary: 'Add a new member to the current tenant with a chosen role',
        tags: ['Team'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'role', type: 'string', enum: ['owner', 'admin', 'accountant', 'viewer']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Member created'),
            new OA\Response(response: 409, description: 'Email already registered'),
            new OA\Response(response: 422, description: 'Invalid input or unknown role'),
        ]
    )]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(fn (): ResponseInterface => $this->json(201, $this->teamService->create($this->body($request))));
    }

    private function respond(\Closure $action): ResponseInterface
    {
        try {
            return $action();
        } catch (TeamException $e) {
            return JsonErrorResponse::build($e->status, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed>|array<int, array<string, mixed>> $payload */
    private function json(int $status, mixed $payload): ResponseInterface
    {
        $response = $this->psr17Factory->createResponse($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
