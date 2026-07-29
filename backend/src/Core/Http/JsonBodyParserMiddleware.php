<?php

declare(strict_types=1);

namespace App\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * nyholm/psr7's ServerRequestCreator only populates getParsedBody() for
 * classic form content types (application/x-www-form-urlencoded,
 * multipart/form-data) — PSR-7 leaves JSON bodies to middleware on
 * purpose. This decodes `application/json` request bodies and attaches
 * them via withParsedBody(), so controllers only ever read
 * $request->getParsedBody(), never json_decode() themselves.
 *
 * Global middleware: harmless for requests with no JSON body (GET,
 * empty POST) — they pass through unchanged.
 */
final class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!str_contains($request->getHeaderLine('Content-Type'), 'application/json')) {
            return $handler->handle($request);
        }

        $body = (string) $request->getBody();

        if ($body === '') {
            return $handler->handle($request->withParsedBody([]));
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return JsonErrorResponse::build(400, 'Malformed JSON body.');
        }

        return $handler->handle($request->withParsedBody(is_array($decoded) ? $decoded : []));
    }
}
