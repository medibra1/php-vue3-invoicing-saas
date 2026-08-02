<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog;

use Nyholm\Psr7\Factory\Psr17Factory;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ActivityLogController
{
    private readonly Psr17Factory $psr17Factory;

    public function __construct(private readonly ActivityLogRepository $activityLogs)
    {
        $this->psr17Factory = new Psr17Factory();
    }

    #[OA\Get(
        path: '/api/v1/activity-logs',
        summary: 'Paginated activity log for the current tenant (status changes, payments, conversions)',
        tags: ['ActivityLog'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'items[], total, page, perPage')]
    )]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['perPage'] ?? 20);

        $response = $this->psr17Factory->createResponse(200)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($this->activityLogs->paginate($page, $perPage), JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
