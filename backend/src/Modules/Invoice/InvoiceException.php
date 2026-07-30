<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

/**
 * An expected failure in invoice creation/editing/status transitions.
 * Same pattern as Quote\QuoteException — default 422 since most
 * invoice failures are validation, not "not found".
 */
final class InvoiceException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
