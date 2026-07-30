<?php

declare(strict_types=1);

namespace App\Modules\Payment;

use App\Core\Database\Repository;

final class PaymentRepository extends Repository
{
    protected function table(): string
    {
        return 'payments';
    }

    /** @return array<int, array<string, mixed>> */
    public function allForInvoice(int $invoiceId): array
    {
        return $this->query()
            ->where('invoice_id', '=', $invoiceId)
            ->whereNull('deleted_at')
            ->orderBy('paid_at', 'DESC')
            ->get();
    }

    /**
     * Computed live rather than stored — see the migration's class doc.
     * A tenant's payments are never numerous enough per invoice to
     * justify a SQL SUM() over the small in-PHP array_sum() here.
     */
    public function sumForInvoice(int $invoiceId): float
    {
        $rows = $this->query()->where('invoice_id', '=', $invoiceId)->whereNull('deleted_at')->get();

        return array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $rows));
    }
}
