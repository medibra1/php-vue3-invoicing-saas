<?php

declare(strict_types=1);

namespace App\Modules\Stats;

use App\Core\Database\Connection;
use App\Core\Database\CurrentTenant;
use App\Modules\Payment\PaymentRepository;
use DateTimeImmutable;

/**
 * Read-only aggregate reads spanning quotes/invoices/payments — doesn't
 * fit the one-table-per-Repository shape, so it queries Connection::table()
 * directly (forTenant() is available on any QueryBuilder, not just
 * through a Repository subclass) rather than extending the base
 * Repository. Everything is plain QueryBuilder + PHP array aggregation
 * (no raw SQL, no GROUP BY/JOIN) — same "compute live, keep it portable"
 * choice as PaymentRepository::sumForInvoice(), and it keeps this
 * service exercisable by the same SQLite-backed test setup as every
 * other module (MySQL-only functions like DATE_FORMAT would break that).
 *
 * Revenue is real cash collected (SUM of payments), not invoice face
 * value — a partially_paid invoice's uncollected balance isn't revenue
 * yet.
 */
final class StatsService
{
    private const REVENUE_MONTHS = 6;

    public function __construct(
        private readonly Connection $connection,
        private readonly CurrentTenant $tenant,
        private readonly PaymentRepository $payments
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $payments = $this->allPayments();

        return [
            'revenue' => $this->revenue($payments),
            'overdue' => $this->overdue(),
            'draftQuotes' => $this->countByStatus('quotes', 'draft'),
            'quoteAcceptanceRate' => $this->quoteAcceptanceRate(),
            'revenueByMonth' => $this->revenueByMonth($payments),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function allPayments(): array
    {
        return $this->connection->table('payments')
            ->forTenant($this->tenant->id)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @return array{thisMonth: float, allTime: float}
     */
    private function revenue(array $payments): array
    {
        $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $allTime = 0.0;
        $thisMonth = 0.0;

        foreach ($payments as $payment) {
            $amount = (float) $payment['amount'];
            $allTime += $amount;

            if ((string) $payment['paid_at'] >= $monthStart) {
                $thisMonth += $amount;
            }
        }

        return ['thisMonth' => round($thisMonth, 2), 'allTime' => round($allTime, 2)];
    }

    /** @return array{count: int, total: float} */
    private function overdue(): array
    {
        $invoices = $this->connection->table('invoices')
            ->forTenant($this->tenant->id)
            ->where('status', '=', 'overdue')
            ->whereNull('deleted_at')
            ->get();

        $total = 0.0;

        foreach ($invoices as $invoice) {
            $total += (float) $invoice['total'] - $this->payments->sumForInvoice((int) $invoice['id']);
        }

        return ['count' => count($invoices), 'total' => round($total, 2)];
    }

    private function countByStatus(string $table, string $status): int
    {
        return $this->connection->table($table)
            ->forTenant($this->tenant->id)
            ->where('status', '=', $status)
            ->whereNull('deleted_at')
            ->count();
    }

    /** Share of decided quotes (accepted/rejected/expired) that were accepted. Null if none decided yet. */
    private function quoteAcceptanceRate(): ?float
    {
        $accepted = $this->countByStatus('quotes', 'accepted');
        $rejected = $this->countByStatus('quotes', 'rejected');
        $expired = $this->countByStatus('quotes', 'expired');
        $decided = $accepted + $rejected + $expired;

        return $decided > 0 ? round($accepted / $decided, 4) : null;
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @return array<int, array{month: string, revenue: float}>
     */
    private function revenueByMonth(array $payments): array
    {
        $byMonth = [];

        foreach ($payments as $payment) {
            $month = substr((string) $payment['paid_at'], 0, 7); // 'YYYY-MM'
            $byMonth[$month] = ($byMonth[$month] ?? 0.0) + (float) $payment['amount'];
        }

        $series = [];

        for ($i = self::REVENUE_MONTHS - 1; $i >= 0; $i--) {
            $month = (new DateTimeImmutable('first day of this month'))->modify("-{$i} months")->format('Y-m');
            $series[] = ['month' => $month, 'revenue' => round($byMonth[$month] ?? 0.0, 2)];
        }

        return $series;
    }
}
