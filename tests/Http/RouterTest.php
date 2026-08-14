<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Http;

use Jolicode\CastorApi\Http\Router;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class RouterTest extends TestCase
{
    private string $projectRoot;

    private OpenApiLoader $loader;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/castor-api-router-' . uniqid('', true);
        $openapiDir = $this->projectRoot . '/.castor/api';
        mkdir($openapiDir, 0o777, true);

        file_put_contents($openapiDir . '/openapi.json', json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/health' => [
                    'get' => [
                        'operationId' => 'castor.health',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/tasks/hello/run' => [
                    'post' => [
                        'operationId' => 'hello',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->loader = new OpenApiLoader($openapiDir . '/openapi.json');
    }

    protected function tearDown(): void
    {
        $openapiDir = $this->projectRoot . '/.castor/api';

        if (is_dir($openapiDir)) {
            unlink($openapiDir . '/openapi.json');
            rmdir($openapiDir);
            rmdir($this->projectRoot . '/.castor');
            rmdir($this->projectRoot);
        }
    }

    public function testReturnsNotFoundForUnknownRoute(): void
    {
        $router = new Router();
        $response = $router->handle(Request::create('/unknown'), $this->loader, '/tmp/package');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testReturnsHealthCheck(): void
    {
        $router = new Router();
        $response = $router->handle(Request::create('/health', 'GET'), $this->loader, '/tmp/package');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status']);
    }

    public function testReturnsOpenApiSpec(): void
    {
        $router = new Router();
        $response = $router->handle(Request::create('/openapi.json', 'GET'), $this->loader, '/tmp/package');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('3.1.0', $payload['openapi']);
    }

    public function testRejectsUnauthorizedRequestWhenTokenConfigured(): void
    {
        putenv('CASTOR_API_TOKEN=secret');

        try {
            $router = new Router();
            $response = $router->handle(Request::create('/health', 'GET'), $this->loader, '/tmp/package');

            self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        } finally {
            putenv('CASTOR_API_TOKEN');
        }
    }

    public function testAllowsAuthorizedRequestWhenTokenConfigured(): void
    {
        putenv('CASTOR_API_TOKEN=secret');

        try {
            $router = new Router();
            $request = Request::create('/health', 'GET', [], [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer secret',
            ]);
            $response = $router->handle($request, $this->loader, '/tmp/package');

            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        } finally {
            putenv('CASTOR_API_TOKEN');
        }
    }
}
