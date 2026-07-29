<?php

declare(strict_types=1);

namespace App\Core\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Sends a PSR-7 response through PHP's SAPI: status line, headers, then
 * body, streamed in chunks rather than loaded fully into memory (matters
 * once this emits large PDF responses for invoices).
 *
 * Kept separate from Kernel so the routing/middleware/dispatch logic in
 * Kernel::handle() stays headers-free and fully testable in isolation —
 * only Kernel::run(), the real HTTP entry point, ever touches the SAPI.
 */
final class ResponseEmitter
{
    private const CHUNK_SIZE = 8192;

    public function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header("{$name}: {$value}", false);
                }
            }
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(self::CHUNK_SIZE);
        }
    }
}
