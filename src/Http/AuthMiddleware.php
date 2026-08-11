<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthMiddleware
{
    public static function authenticate(Request $request, ?string $expectedToken): ?Response
    {
        if (null === $expectedToken || '' === $expectedToken) {
            return null;
        }

        $authorization = $request->headers->get('Authorization') ?? '';

        if ('' === $authorization || !preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return self::unauthorized('Missing or invalid Authorization header.');
        }

        if (!hash_equals($expectedToken, $matches[1])) {
            return self::unauthorized('Invalid API token.');
        }

        return null;
    }

    private static function unauthorized(string $message): Response
    {
        return new Response(
            json_encode(['error' => $message], JSON_THROW_ON_ERROR),
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/json'],
        );
    }
}
