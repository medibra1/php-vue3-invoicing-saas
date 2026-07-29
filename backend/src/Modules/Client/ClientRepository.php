<?php

declare(strict_types=1);

namespace App\Modules\Client;

use App\Core\Database\Repository;

final class ClientRepository extends Repository
{
    protected function table(): string
    {
        return 'clients';
    }

    /**
     * Name-only: QueryBuilder has no OR/grouping support yet, so
     * matching name-or-email in a single query isn't possible — worth
     * revisiting once it does.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(?string $term): array
    {
        $query = $this->query()->whereNull('deleted_at')->orderBy('name', 'ASC');

        if ($term !== null && trim($term) !== '') {
            $query->whereLike('name', trim($term));
        }

        return $query->get();
    }
}
