<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;

/**
 * Not a subclass of the base Repository: that class's find()/all()/
 * delete() all assume a `deleted_at` column, which activity_logs
 * deliberately doesn't have (see the migration's class doc) — same
 * legitimate exception as TenantRepository/UserRepository/StatsService.
 */
final class ActivityLogRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentTenant $tenant
    ) {
    }

    /** Fire-and-forget: called from other modules' controllers as a side effect of a successful write. */
    public function log(?int $userId, string $action, string $subjectType, int $subjectId, string $description): void
    {
        $this->connection->table('activity_logs')->forTenant($this->tenant->id)->insert([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
        ]);
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int} */
    public function paginate(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $items = $this->connection->table('activity_logs')
            ->forTenant($this->tenant->id)
            ->orderBy('id', 'DESC')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $total = $this->connection->table('activity_logs')->forTenant($this->tenant->id)->count();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
