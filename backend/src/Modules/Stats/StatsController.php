<?php

declare(strict_types=1);

namespace App\Modules\Stats;

use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

final class StatsController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly StatsService $statsService)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/stats/dashboard',
        summary: 'Snapshot + time-series metrics for the dashboard (revenue, overdue, quotes)',
        tags: ['Stats'],
        responses: [new OA\Response(response: 200, description: 'Dashboard metrics')]
    )]
    public function dashboard(): ResponseInterface
    {
        $response = $this->psr17Factory->createResponse(200)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($this->statsService->dashboard(), JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
