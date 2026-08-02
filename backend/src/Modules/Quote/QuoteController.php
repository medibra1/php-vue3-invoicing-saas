<?php

declare(strict_types=1);

namespace App\Modules\Quote;

use App\Core\Http\JsonErrorResponse;
use App\Modules\ActivityLog\ActivityLogRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class QuoteController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(
        private readonly QuoteRepository $quotes,
        private readonly QuoteService $quoteService,
        private readonly QuoteToInvoiceConverter $converter,
        private readonly ActivityLogRepository $activityLogs
    ) {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/quotes',
        summary: 'List quotes for the current tenant (without line items)',
        tags: ['Quotes'],
        responses: [new OA\Response(response: 200, description: 'Array of quotes')]
    )]
    public function index(): ResponseInterface
    {
        return $this->json(200, $this->quotes->all());
    }

    #[OA\Get(
        path: '/api/v1/quotes/{id}',
        summary: 'Show a single quote with its line items',
        tags: ['Quotes'],
        responses: [
            new OA\Response(response: 200, description: 'The quote, with items'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): ResponseInterface
    {
        return $this->respond(fn (): ResponseInterface => $this->json(200, $this->quoteService->findOrFail((int) $id)));
    }

    #[OA\Post(
        path: '/api/v1/quotes',
        summary: 'Create a draft quote with line items',
        tags: ['Quotes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_id', 'items'],
                properties: [
                    new OA\Property(property: 'client_id', type: 'integer'),
                    new OA\Property(property: 'issue_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'expiry_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'quantity', type: 'number'),
                                new OA\Property(property: 'unit_price', type: 'number'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created, with items'),
            new OA\Response(response: 422, description: 'Invalid client or items'),
        ]
    )]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        return $this->respond(fn (): ResponseInterface => $this->json(201, $this->quoteService->create($this->body($request))));
    }

    #[OA\Put(
        path: '/api/v1/quotes/{id}',
        summary: 'Replace a draft quote (client, dates, notes, items)',
        tags: ['Quotes'],
        responses: [
            new OA\Response(response: 200, description: 'Updated, with items'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Not a draft, or invalid input'),
        ]
    )]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->respond(
            fn (): ResponseInterface => $this->json(200, $this->quoteService->update((int) $id, $this->body($request)))
        );
    }

    #[OA\Delete(
        path: '/api/v1/quotes/{id}',
        summary: 'Soft-delete a draft quote',
        tags: ['Quotes'],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Not a draft'),
        ]
    )]
    public function destroy(string $id): ResponseInterface
    {
        return $this->respond(function () use ($id): ResponseInterface {
            $this->quoteService->delete((int) $id);

            return $this->psr17Factory->createResponse(204);
        });
    }

    #[OA\Post(
        path: '/api/v1/quotes/{id}/status',
        summary: 'Move a quote to its next status (draft->sent->accepted/rejected/expired)',
        tags: ['Quotes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['status'], properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['sent', 'accepted', 'rejected', 'expired']),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Transition not allowed from the current status'),
        ]
    )]
    public function updateStatus(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->respond(function () use ($request, $id): ResponseInterface {
            $status = (string) ($this->body($request)['status'] ?? '');
            $quote = $this->quoteService->transition((int) $id, $status);

            $this->activityLogs->log(
                $this->userId($request),
                'quote.status_changed',
                'Quote',
                (int) $quote['id'],
                "Quote {$quote['number']} moved to {$quote['status']}"
            );

            return $this->json(200, $quote);
        });
    }

    #[OA\Post(
        path: '/api/v1/quotes/{id}/convert',
        summary: 'Convert an accepted quote into a new draft invoice',
        tags: ['Quotes'],
        responses: [
            new OA\Response(response: 201, description: 'The newly created invoice, with items'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Quote is not accepted'),
        ]
    )]
    public function convert(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->respond(function () use ($request, $id): ResponseInterface {
            $quote = $this->quoteService->findOrFail((int) $id);
            $invoice = $this->converter->convert((int) $id);

            $this->activityLogs->log(
                $this->userId($request),
                'quote.converted',
                'Quote',
                (int) $quote['id'],
                "Quote {$quote['number']} converted to invoice {$invoice['number']}"
            );

            return $this->json(201, $invoice);
        });
    }

    private function userId(ServerRequestInterface $request): ?int
    {
        $claims = $request->getAttribute('authClaims');
        $userId = is_array($claims) ? ($claims['sub'] ?? null) : null;

        return $userId === null ? null : (int) $userId;
    }

    private function respond(\Closure $action): ResponseInterface
    {
        try {
            return $action();
        } catch (QuoteException $e) {
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
