<?php

declare(strict_types=1);

namespace App\Modules\Payment;

/**
 * An expected failure in payment recording. Same pattern as
 * Invoice\InvoiceException — default 422 since most payment failures are
 * validation (invalid amount, invoice can't take a payment), not "not
 * found".
 */
final class PaymentException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
