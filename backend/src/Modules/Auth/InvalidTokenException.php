<?php

declare(strict_types=1);

namespace App\Modules\Auth;

/**
 * A JWT failed to verify — expired, bad signature, malformed, or wrong
 * algorithm. AuthMiddleware catches this one type uniformly and returns
 * a 401; the client never needs (or should get) the specific reason.
 */
final class InvalidTokenException extends \RuntimeException
{
}
