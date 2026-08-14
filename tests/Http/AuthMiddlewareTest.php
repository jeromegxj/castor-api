<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Http;

use Jolicode\CastorApi\Http\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class AuthMiddlewareTest extends TestCase
{
    public function testAllowsRequestWhenNoTokenConfigured(): void
    {
        $response = AuthMiddleware::authenticate(Request::create('/'), null);

        self::assertNull($response);
    }

    public function testRejectsMissingAuthorizationHeader(): void
    {
        $response = AuthMiddleware::authenticate(Request::create('/'), 'secret');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Missing or invalid Authorization header', (string) $response->getContent());
    }

    public function testRejectsInvalidToken(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
        ]);

        $response = AuthMiddleware::authenticate($request, 'secret');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Invalid API token', (string) $response->getContent());
    }

    public function testAllowsValidToken(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer secret',
        ]);

        $response = AuthMiddleware::authenticate($request, 'secret');

        self::assertNull($response);
    }
}
