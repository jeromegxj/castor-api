<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\OpenApi;

use Jolicode\CastorApi\Attribute\AsApi;
use Jolicode\CastorApi\OpenApi\OpenApiExporter;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Jolicode\CastorApi\OpenApi\OperationKind;
use Jolicode\CastorApi\Registry\ApiTaskRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @coversNothing
 */
final class OpenApiExporterTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiTaskRegistry::reset();
    }

    public function testAsyncEndpointExportsStartAndStatusPaths(): void
    {
        ApiTaskRegistry::registerEndpoint(
            taskName: 'demo:slow',
            api: new AsApi(async: true),
            description: 'Slow task',
            workingDirectory: null,
            schema: [
                'arguments' => [],
                'options' => [[
                    'name' => 'seconds',
                    'shortcut' => null,
                    'description' => '',
                    'acceptValue' => true,
                    'isValueRequired' => false,
                    'isArray' => false,
                    'isFlag' => false,
                    'default' => 2,
                ]],
            ],
        );

        $spec = OpenApiExporter::build('/tmp/project');
        $paths = $spec->paths;

        self::assertTrue(isset($paths['/tasks/demo%3Aslow/run']->post));
        self::assertTrue(isset($paths['/tasks/demo%3Aslow/start']->post));
        self::assertTrue(isset($paths['/tasks/demo%3Aslow/status/{runId}']->get));
        self::assertSame('demo:slow.start', $paths['/tasks/demo%3Aslow/start']->post->operationId);
        self::assertSame('demo:slow.status', $paths['/tasks/demo%3Aslow/status/{runId}']->get->operationId);
    }

    public function testSyncEndpointExportsRunPathOnly(): void
    {
        ApiTaskRegistry::registerEndpoint(
            taskName: 'hello',
            api: new AsApi(),
            description: 'Hello task',
            workingDirectory: null,
        );

        $spec = OpenApiExporter::build('/tmp/project');
        $paths = $spec->paths;

        self::assertTrue(isset($paths['/tasks/hello/run']->post));
        self::assertFalse(isset($paths['/tasks/hello/start']));
        self::assertFalse(isset($paths['/tasks/hello/status/{runId}']));
    }

    public function testStatusRouteExtractsRunId(): void
    {
        $projectRoot = sys_get_temp_dir() . '/castor-api-openapi-' . uniqid('', true);
        $openapiDir = $projectRoot . '/.castor/api';
        mkdir($openapiDir, 0o777, true);

        file_put_contents($openapiDir . '/openapi.json', json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/tasks/demo:slow/status/{runId}' => [
                    'get' => [
                        'operationId' => 'demo:slow.status',
                        'parameters' => [[
                            'name' => 'runId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ]],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $loader = new OpenApiLoader($openapiDir . '/openapi.json');
        $context = $loader->match(Request::create('/tasks/demo:slow/status/abc-123', 'GET'));

        self::assertNotNull($context);
        self::assertSame(OperationKind::Status, $context->kind);
        self::assertSame('demo:slow', $context->taskName);
        self::assertSame('abc-123', $context->runId);

        rmdir($openapiDir);
        rmdir($projectRoot . '/.castor');
        rmdir($projectRoot);
    }
}
