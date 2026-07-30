<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Pure rendering: invoice (with items) + client data in, PDF bytes out.
 * No DB access here on purpose — fetching the client is InvoiceController's
 * job, keeping this class trivially testable and reusable (e.g. from a
 * future "email this invoice" feature) without a Connection.
 *
 * dompdf chosen over mpdf/wkhtmltopdf/headless-Chrome: pure PHP, no
 * external binary or service to install, consistent with every other
 * dependency choice in this project favoring "clone and run" simplicity.
 */
final class InvoicePdfGenerator
{
    /**
     * @param array<string, mixed> $invoice Must include 'items' (see HasLineItems::findWithItems()).
     * @param array<string, mixed> $client
     */
    public function render(array $invoice, array $client): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($invoice, $client));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed> $client
     */
    private function html(array $invoice, array $client): string
    {
        $rows = '';

        /** @var array<string, mixed> $item */
        foreach ($invoice['items'] as $item) {
            $rows .= '<tr>'
                . '<td>' . $this->escape($item['description']) . '</td>'
                . '<td class="num">' . $this->escape($item['quantity']) . '</td>'
                . '<td class="num">' . $this->escape($item['unit_price']) . '</td>'
                . '<td class="num">' . $this->escape($item['line_total']) . '</td>'
                . '</tr>';
        }

        $notes = $invoice['notes'] !== null
            ? '<p class="notes">' . nl2br($this->escape($invoice['notes'])) . '</p>'
            : '';

        $number = $this->escape($invoice['number']);
        $clientName = $this->escape($client['name'] ?? 'Unknown client');
        $issueDate = $this->escape($invoice['issue_date']);
        $dueDate = $invoice['due_date'] !== null ? $this->escape($invoice['due_date']) : '—';
        $total = $this->escape($invoice['total']);

        return <<<HTML
            <html>
            <head>
                <style>
                    body { font-family: Helvetica, sans-serif; font-size: 12px; color: #1f2937; }
                    h1 { font-size: 22px; color: #185FA5; margin-bottom: 4px; }
                    .meta { color: #6b7280; margin-bottom: 24px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
                    th, td { padding: 8px 4px; border-bottom: 1px solid #e5e7eb; text-align: left; }
                    th { color: #6b7280; font-weight: normal; }
                    .num { text-align: right; }
                    .total { text-align: right; font-weight: bold; font-size: 14px; margin-top: 16px; }
                    .notes { margin-top: 24px; color: #4b5563; }
                </style>
            </head>
            <body>
                <h1>Invoice {$number}</h1>
                <p class="meta">
                    Bill to: {$clientName}<br>
                    Issue date: {$issueDate} &nbsp;&nbsp; Due date: {$dueDate}
                </p>
                <table>
                    <thead>
                        <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Line total</th></tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
                <p class="total">Total: {$total}</p>
                {$notes}
            </body>
            </html>
            HTML;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
