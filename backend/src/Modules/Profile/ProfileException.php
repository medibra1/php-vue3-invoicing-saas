<?php

declare(strict_types=1);

namespace App\Modules\Profile;

/**
 * An expected failure in profile/avatar/password updates. Same pattern
 * as Payment\PaymentException — default 422, most Profile failures are
 * validation (bad avatar file, wrong current password), not "not found".
 */
final class ProfileException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
