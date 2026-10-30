<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Http;

use Castor\Api\Http\TaskStatusHandler;
use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\OpenApi\OperationContext;
use Castor\Api\OpenApi\OperationKind;
use Castor\Api\Run\RunStatus;
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
final class TaskStatusHandlerTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('status');
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->projectRoot);
    }

    public function testReturnsPendingRun(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);

        $openapiPath = $this->projectRoot . '/.castor/api/openapi.json';
        file_put_contents($openapiPath, json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
        ], JSON_THROW_ON_ERROR));

        $loader = new OpenApiLoader($openapiPath);
        $operation = new OperationContext(
            operationId: 'demo:slow.status',
            taskName: 'demo:slow',
            kind: OperationKind::Status,
            path: '/tasks/demo:slow/status/' . $record->id,
            method: 'GET',
            requestSchema: null,
            workingDirectory: null,
            runId: $record->id,
        );

        $response = TaskStatusHandler::status($loader, $operation, Request::create($operation->path, 'GET'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($record->id, $payload['id']);
        self::assertSame('pending', $payload['status']);
        self::assertNull($payload['stdout']);
    }

    public function testReturnsNotFoundForWrongTask(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);

        $openapiPath = $this->projectRoot . '/.castor/api/openapi.json';
        file_put_contents($openapiPath, json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
        ], JSON_THROW_ON_ERROR));

        $loader = new OpenApiLoader($openapiPath);
        $operation = new OperationContext(
            operationId: 'other.status',
            taskName: 'other',
            kind: OperationKind::Status,
            path: '/tasks/other/status/' . $record->id,
            method: 'GET',
            requestSchema: null,
            workingDirectory: null,
            runId: $record->id,
        );

        $response = TaskStatusHandler::status($loader, $operation, Request::create($operation->path, 'GET'));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testReturns422ForFailedRun(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);
        $record->status = RunStatus::Failed;
        $record->exitCode = 1;
        $record->stderr = 'Task failed';
        $record->stdout = '';
        $record->durationMs = 42;
        $store->save($record);

        $openapiPath = $this->projectRoot . '/.castor/api/openapi.json';
        file_put_contents($openapiPath, json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
        ], JSON_THROW_ON_ERROR));

        $loader = new OpenApiLoader($openapiPath);
        $operation = new OperationContext(
            operationId: 'demo:slow.status',
            taskName: 'demo:slow',
            kind: OperationKind::Status,
            path: '/tasks/demo:slow/status/' . $record->id,
            method: 'GET',
            requestSchema: null,
            workingDirectory: null,
            runId: $record->id,
        );

        $response = TaskStatusHandler::status($loader, $operation, Request::create($operation->path, 'GET'));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        /** @var array<string, mixed> $payload */
        $payload = json_decode($response->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($record->id, $payload['id']);
        self::assertSame('failed', $payload['status']);
        self::assertSame(1, $payload['exitCode']);
        self::assertSame('Task failed', $payload['stderr']);
    }
}
