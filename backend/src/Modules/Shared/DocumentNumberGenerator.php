<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;

/**
 * Shared by Quote and Invoice — CLAUDE.md's schema decisions describe
 * "a dedicated InvoiceNumberGenerator service" for both, but naming a
 * shared class after only one of its two consumers would be misleading;
 * this lives in Modules\Shared instead since neither Quote nor Invoice
 * should depend on the other's module for it.
 *
 * Format: {PREFIX}-{year}-{5-digit sequence}, e.g. QUO-2026-00042. The
 * sequence is a simple per-tenant, per-table monotonic counter — COUNT
 * of every row ever inserted for this tenant on this table, including
 * soft-deleted ones, since a number is never reused once issued — not
 * reset per calendar year, so the year segment reflects when the
 * number was issued rather than a per-year sequence. UNIQUE(tenant_id,
 * number) on both tables is the backstop against the (small, no
 * write-lock here) race window under concurrent creates: a collision
 * fails the INSERT loudly instead of silently duplicating a number.
 */
final class DocumentNumberGenerator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentTenant $tenant
    ) {
    }

    public function next(string $table, string $prefix): string
    {
        $sequence = $this->connection->table($table)->forTenant($this->tenant->id)->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, date('Y'), $sequence);
    }
}
