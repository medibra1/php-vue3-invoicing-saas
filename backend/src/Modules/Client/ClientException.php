<?php

declare(strict_types=1);

namespace App\Modules\Client;

/**
 * An expected failure in client CRUD (not found, invalid input).
 * Carries the HTTP status it maps to, same pattern as Auth\AuthException.
 */
final class ClientException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 404)
    {
        parent::__construct($message);
    }
}
