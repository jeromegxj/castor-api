<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Http;

use Jolicode\CastorApi\Http\TaskStatusHandler;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Jolicode\CastorApi\OpenApi\OperationContext;
use Jolicode\CastorApi\OpenApi\OperationKind;
use Jolicode\CastorApi\Run\RunStatus;
use Jolicode\CastorApi\Run\RunStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class TaskStatusHandlerTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/castor-api-status-' . uniqid('', true);
        mkdir($this->projectRoot . '/.castor/api/runs', 0o777, true);
    }

    protected function tearDown(): void
    {
        $runsDir = $this->projectRoot . '/.castor/api/runs';

        if (is_dir($runsDir)) {
            foreach (glob($runsDir . '/*.json') ?: [] as $file) {
                unlink($file);
            }

            rmdir($runsDir);
            rmdir($this->projectRoot . '/.castor/api');
            rmdir($this->projectRoot . '/.castor');
            rmdir($this->projectRoot);
        }
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
