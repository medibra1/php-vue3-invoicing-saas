<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Thrown when a mutating query (update/delete) has no WHERE clause at
 * all. Without this guard, a missing `->where(...)` call — e.g. a typo
 * that silently drops the condition — would update or delete every row
 * in the table instead of failing loudly.
 */
final class UnsafeQueryException extends \RuntimeException
{
}
