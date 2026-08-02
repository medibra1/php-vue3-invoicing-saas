<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Core\Http\JsonErrorResponse;
use App\Modules\ActivityLog\ActivityLogRepository;
use App\Modules\Client\ClientRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class InvoiceController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePdfGenerator $pdfGenerator,
        private readonly ClientRepository $clients,
        private readonly ActivityLogRepository $activityLogs
    ) {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/invoices',
        summary: 'List invoices for the current tenant (without line items)',
        tags: ['Invoices'],
        responses: [new OA\Response(response: 200, description: 'Array of invoices')]
    )]
    public function index(): ResponseInterface
    {
        return $this->json(200, $this->invoices->all());
    }

    #[OA\Get(
        path: '/api/v1/invoices/{id}',
        summary: 'Show a single invoice with its line items',
        tags: ['Invoices'],
        responses: [
            new OA\Response(response: 200, description: 'The invoice, with items'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): ResponseInterface
    {
        return $this->respond(fn (): ResponseInterface => $this->json(200, $this->invoiceService->findOrFail((int) $id)));
    }

    #[OA\Post(
        path: '/api/v1/invoices',
        summary: 'Create a draft invoice with line items (standalone, not via a quote)',
        tags: ['Invoices'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_id', 'items'],
                properties: [
                    new OA\Property(property: 'client_id', type: 'integer'),
                    new OA\Property(property: 'issue_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
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
        return $this->respond(fn (): ResponseInterface => $this->json(201, $this->invoiceService->create($this->body($request))));
    }

    #[OA\Put(
        path: '/api/v1/invoices/{id}',
        summary: 'Replace a draft invoice (client, dates, notes, items)',
        tags: ['Invoices'],
        responses: [
            new OA\Response(response: 200, description: 'Updated, with items'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Not a draft, or invalid input'),
        ]
    )]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->respond(
            fn (): ResponseInterface => $this->json(200, $this->invoiceService->update((int) $id, $this->body($request)))
        );
    }

    #[OA\Delete(
        path: '/api/v1/invoices/{id}',
        summary: 'Soft-delete a draft invoice',
        tags: ['Invoices'],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Not a draft'),
        ]
    )]
    public function destroy(string $id): ResponseInterface
    {
        return $this->respond(function () use ($id): ResponseInterface {
            $this->invoiceService->delete((int) $id);

            return $this->psr17Factory->createResponse(204);
        });
    }

    #[OA\Post(
        path: '/api/v1/invoices/{id}/status',
        summary: 'Move an invoice to its next status (draft->sent->paid/overdue/cancelled)',
        tags: ['Invoices'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['status'], properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['sent', 'paid', 'overdue', 'cancelled']),
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
            $invoice = $this->invoiceService->transition((int) $id, $status);

            $this->activityLogs->log(
                $this->userId($request),
                'invoice.status_changed',
                'Invoice',
                (int) $invoice['id'],
                "Invoice {$invoice['number']} moved to {$invoice['status']}"
            );

            return $this->json(200, $invoice);
        });
    }

    #[OA\Get(
        path: '/api/v1/invoices/{id}/pdf',
        summary: 'Download the invoice as a PDF',
        tags: ['Invoices'],
        responses: [
            new OA\Response(response: 200, description: 'application/pdf'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function pdf(string $id): ResponseInterface
    {
        return $this->respond(function () use ($id): ResponseInterface {
            $invoice = $this->invoiceService->findOrFail((int) $id);
            $client = $this->clients->find((int) $invoice['client_id']) ?? [];

            $response = $this->psr17Factory->createResponse(200)
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', "attachment; filename=\"{$invoice['number']}.pdf\"");
            $response->getBody()->write($this->pdfGenerator->render($invoice, $client));

            return $response;
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
        } catch (InvoiceException $e) {
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
