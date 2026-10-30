<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Http;

use Castor\Api\Http\Router;
use Castor\Api\Http\TaskStartHandler;
use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\Run\RunStore;
use Castor\Api\Tests\TemporaryProjectTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversNothing]
final class RunEndpointsTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    private OpenApiLoader $loader;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('run-endpoints');

        file_put_contents($this->projectRoot . '/.castor/api/openapi.json', json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/tasks/shop%3Agenerate/start' => [
                    'post' => [
                        'operationId' => 'shop:generate.start',
                        'x-castor-locks' => ['shop:{project}'],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['project' => ['type' => 'string']],
                            'x-castor-arguments' => [],
                            'x-castor-options' => ['project'],
                        ]]]],
                        'responses' => ['202' => ['description' => 'Accepted']],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->loader = new OpenApiLoader($this->projectRoot . '/.castor/api/openapi.json');
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->projectRoot);
    }

    public function testRunsListsTheActiveRuns(): void
    {
        $store = new RunStore($this->projectRoot);
        $run = $store->create('shop:generate', [], 'castor', null, ['shop:acme'], ['project' => 'acme']);

        $response = new Router()->handle(Request::create('/runs', 'GET'), $this->loader, $this->projectRoot);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array{runs: list<array<string, mixed>>} $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $data['runs']);
        self::assertSame($run->id, $data['runs'][0]['id']);
        self::assertSame(['shop:acme'], $data['runs'][0]['locks']);
    }

    public function testStartIsRefusedWhenALockIsHeld(): void
    {
        $store = new RunStore($this->projectRoot);
        $run = $store->create('shop:generate', [], 'castor', null, ['shop:acme'], ['project' => 'acme']);

        $request = Request::create('/tasks/shop%3Agenerate/start', 'POST', content: '{"project": "acme"}');
        $request->headers->set('Content-Type', 'application/json');
        $operation = $this->loader->match($request);
        self::assertNotNull($operation);
        self::assertSame(['shop:{project}'], $operation->locks);

        $response = TaskStartHandler::start($this->loader, $operation, $request, $this->projectRoot);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        /** @var array{error: string, conflict: array<string, mixed>} $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($run->id, $data['conflict']['id']);
        self::assertStringContainsString('shop:acme', $data['error']);
    }
}
