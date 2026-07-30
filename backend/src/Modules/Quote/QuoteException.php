<?php

declare(strict_types=1);

namespace App\Modules\Quote;

/**
 * An expected failure in quote creation/editing/status transitions.
 * Carries the HTTP status it maps to, same pattern as Auth\AuthException
 * and Client\ClientException.
 */
final class QuoteException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
