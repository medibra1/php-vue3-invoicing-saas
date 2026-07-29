<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Verifies a JWT's signature and expiry, and returns its claims as an
 * array.
 *
 * All the different ways firebase/php-jwt can fail (expired, bad
 * signature, malformed payload, wrong algorithm...) are collapsed into
 * a single InvalidTokenException, so AuthMiddleware only has to catch
 * one thing to return a 401 — callers don't need to know or care which
 * specific failure occurred.
 */
final class JwtDecoder
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm = 'HS256'
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidTokenException
     */
    public function decode(string $token): array
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (Throwable $e) {
            throw new InvalidTokenException('Invalid or expired token.', previous: $e);
        }

        return (array) $claims;
    }
}
