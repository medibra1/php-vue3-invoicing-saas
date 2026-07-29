<?php

declare(strict_types=1);

namespace App\Modules\Client;

use App\Core\Http\JsonErrorResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tenant-scoped client CRUD. Every method reaches ClientRepository,
 * which extends the base Repository — there's no code path here that
 * can touch another tenant's clients, by construction.
 */
final class ClientController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly ClientRepository $clients)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/clients',
        summary: 'List clients for the current tenant, optionally filtered by name',
        tags: ['Clients'],
        parameters: [new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Array of clients')]
    )]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $search = isset($query['search']) ? (string) $query['search'] : null;

        return $this->json(200, $this->clients->search($search));
    }

    #[OA\Get(
        path: '/api/v1/clients/{id}',
        summary: 'Show a single client',
        tags: ['Clients'],
        responses: [
            new OA\Response(response: 200, description: 'The client'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): ResponseInterface
    {
        return $this->respond(fn (): ResponseInterface => $this->json(200, $this->findOrFail($id)));
    }

    #[OA\Post(
        path: '/api/v1/clients',
        summary: 'Create a client',
        tags: ['Clients'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Globex Corp'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Invalid input'),
        ]
    )]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(function () use ($request): ResponseInterface {
            $id = $this->clients->create($this->validated($this->body($request)));

            return $this->json(201, $this->clients->find($id));
        });
    }

    #[OA\Put(
        path: '/api/v1/clients/{id}',
        summary: 'Replace a client',
        tags: ['Clients'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Invalid input'),
        ]
    )]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->respond(function () use ($request, $id): ResponseInterface {
            $this->findOrFail($id);
            $this->clients->update((int) $id, $this->validated($this->body($request)));

            return $this->json(200, $this->clients->find((int) $id));
        });
    }

    #[OA\Delete(
        path: '/api/v1/clients/{id}',
        summary: 'Soft-delete a client',
        tags: ['Clients'],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(string $id): ResponseInterface
    {
        return $this->respond(function () use ($id): ResponseInterface {
            $this->findOrFail($id);
            $this->clients->delete((int) $id);

            return $this->psr17Factory->createResponse(204);
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(string $id): array
    {
        $client = $this->clients->find((int) $id);

        if ($client === null) {
            throw new ClientException('Client not found.', 404);
        }

        return $client;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validated(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            throw new ClientException('Name is required.', 422);
        }

        $data = ['name' => $name];

        foreach (['email', 'phone', 'address'] as $field) {
            $value = $input[$field] ?? null;
            $data[$field] = $value === null || $value === '' ? null : (string) $value;
        }

        if ($data['email'] !== null && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ClientException('Invalid email address.', 422);
        }

        return $data;
    }

    private function respond(\Closure $action): ResponseInterface
    {
        try {
            return $action();
        } catch (ClientException $e) {
            return JsonErrorResponse::build($e->status, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /** @param array<string, mixed>|array<int, array<string, mixed>>|null $payload */
    private function json(int $status, mixed $payload): ResponseInterface
    {
        $response = $this->psr17Factory->createResponse($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
