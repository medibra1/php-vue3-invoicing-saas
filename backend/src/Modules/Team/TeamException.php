<?php

declare(strict_types=1);

namespace App\Modules\Team;

/**
 * An expected failure in team member creation. Same pattern as every
 * other module's exception — default 422, explicit status at the
 * throw site (409 for a duplicate email, matching AuthException's
 * register() precedent).
 */
final class TeamException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
