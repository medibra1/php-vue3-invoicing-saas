<?php

declare(strict_types=1);

namespace App\Modules\Payment;

use App\Core\Http\JsonErrorResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PaymentController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly PaymentService $paymentService)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/invoices/{id}/payments',
        summary: 'List payments recorded against an invoice',
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Array of payments'),
            new OA\Response(response: 404, description: 'Invoice not found'),
        ]
    )]
    public function index(string $id): ResponseInterface
    {
        return $this->respond(fn (): ResponseInterface => $this->json(200, $this->paymentService->listForInvoice((int) $id)));
    }

    #[OA\Post(
        path: '/api/v1/invoices/{id}/payments',
        summary: 'Record a payment against an invoice (partial or full)',
        tags: ['Payments'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number'),
                    new OA\Property(property: 'method', type: 'string', nullable: true),
                    new OA\Property(property: 'paid_at', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Recorded; invoice auto-transitions to partially_paid or paid'),
            new OA\Response(response: 404, description: 'Invoice not found'),
            new OA\Response(response: 422, description: 'Invalid amount, or the invoice cannot take a payment'),
        ]
    )]
    public function store(ServerRequestInterface $request, string $id): ResponseInterface
    {
        return $this->respond(
            fn (): ResponseInterface => $this->json(201, $this->paymentService->create((int) $id, $this->body($request)))
        );
    }

    private function respond(\Closure $action): ResponseInterface
    {
        try {
            return $action();
        } catch (PaymentException $e) {
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
