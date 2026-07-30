<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Invoice;

use App\Modules\Invoice\InvoicePdfGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Pure rendering, no DB — exactly why InvoicePdfGenerator was kept
 * free of Connection/repository dependencies (see its class doc).
 */
final class InvoicePdfGeneratorTest extends TestCase
{
    public function testRenderProducesAPdfContainingTheInvoiceNumberAndClientName(): void
    {
        $pdf = (new InvoicePdfGenerator())->render(
            [
                'number' => 'INV-2026-00042',
                'issue_date' => '2026-07-30',
                'due_date' => '2026-08-30',
                'notes' => null,
                'total' => '2500.00',
                'items' => [
                    ['description' => 'Website redesign', 'quantity' => '1.00', 'unit_price' => '2500.00', 'line_total' => '2500.00'],
                ],
            ],
            ['name' => 'Acme Buyer']
        );

        self::assertStringStartsWith('%PDF-', $pdf);

        // The text itself lives in a compressed content stream, not as
        // literal bytes — decompress every stream and check at least
        // one contains what we expect, rather than asserting on the
        // compressed bytes directly.
        self::assertTrue(
            $this->anyStreamContains($pdf, 'INV-2026-00042') && $this->anyStreamContains($pdf, 'Acme Buyer'),
            'Expected the decompressed PDF content stream to contain the invoice number and client name.'
        );
    }

    private function anyStreamContains(string $pdf, string $needle): bool
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        foreach ($matches[1] as $chunk) {
            $decompressed = @gzuncompress($chunk) ?: @gzinflate($chunk);

            if ($decompressed !== false && str_contains($decompressed, $needle)) {
                return true;
            }
        }

        return false;
    }
}
