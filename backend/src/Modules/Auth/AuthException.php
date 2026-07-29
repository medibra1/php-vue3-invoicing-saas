<?php

declare(strict_types=1);

namespace App\Modules\Auth;

/**
 * Any expected failure in the register/login/refresh/logout flow
 * (invalid credentials, email taken, inactive tenant, bad input).
 * Carries the HTTP status it should map to, so AuthController can catch
 * this one type and build the response without a big if/else chain.
 */
final class AuthException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 401)
    {
        parent::__construct($message);
    }
}
