<?php

declare(strict_types=1);

namespace App;

use OpenApi\Attributes as OA;

/**
 * Not instantiated anywhere — purely a place to hang the top-level
 * OpenAPI attributes swagger-php needs (Info/Server), since the spec
 * has to attach to *some* class.
 */
#[OA\Info(title: 'InvoicePro API', version: '0.1.0', description: 'Multi-tenant invoicing/quoting API for freelancers/SMEs.')]
#[OA\Server(url: 'http://localhost:8000/api/v1', description: 'Local development (Docker)')]
final class OpenApi
{
}
