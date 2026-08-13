<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Schema;
use Jolicode\CastorApi\OpenApi\CastorSchemaExtensions;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Jolicode\CastorApi\OpenApi\OpenApiSchemaBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @coversNothing
 */
final class OpenApiSchemaBuilderTest extends TestCase
{
    public function testFromCastorSchemaExportsArgumentsAndOptionsExtensions(): void
    {
        $schema = OpenApiSchemaBuilder::fromCastorSchema([
            'arguments' => [
                [
                    'name' => 'env',
                    'description' => 'Target environment',
                    'required' => true,
                    'isArray' => false,
                    'default' => null,
                ],
            ],
            'options' => [
                [
                    'name' => 'verbose',
                    'shortcut' => null,
                    'description' => 'Enable verbose output',
                    'acceptValue' => false,
                    'isValueRequired' => false,
                    'isArray' => false,
                    'isFlag' => true,
                    'default' => false,
                ],
            ],
        ]);

        self::assertSame(['env'], $schema[CastorSchemaExtensions::ARGUMENTS]);
        self::assertSame(['verbose'], $schema[CastorSchemaExtensions::OPTIONS]);
        self::assertSame(['env'], $schema['required']);
        self::assertSame('string', $schema['properties']['env']['type']);
        self::assertSame('boolean', $schema['properties']['verbose']['type']);
    }

    public function testFromCastorSchemaPreservesArgumentOrder(): void
    {
        $schema = OpenApiSchemaBuilder::fromCastorSchema([
            'arguments' => [
                [
                    'name' => 'first',
                    'description' => '',
                    'required' => true,
                    'isArray' => false,
                    'default' => null,
                ],
                [
                    'name' => 'second',
                    'description' => '',
                    'required' => true,
                    'isArray' => false,
                    'default' => null,
                ],
            ],
            'options' => [],
        ]);

        self::assertSame(['first', 'second'], $schema[CastorSchemaExtensions::ARGUMENTS]);
        self::assertSame([], $schema[CastorSchemaExtensions::OPTIONS]);
    }

    public function testExtensionsSurviveCebeRoundTrip(): void
    {
        $schema = OpenApiSchemaBuilder::fromCastorSchema([
            'arguments' => [
                [
                    'name' => 'arg',
                    'description' => '',
                    'required' => true,
                    'isArray' => false,
                    'default' => null,
                ],
            ],
            'options' => [
                [
                    'name' => 'name',
                    'shortcut' => null,
                    'description' => '',
                    'acceptValue' => true,
                    'isValueRequired' => false,
                    'isArray' => false,
                    'isFlag' => false,
                    'default' => 'world',
                ],
            ],
        ]);

        $openApi = Reader::readFromJson(json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/tasks/hello/run' => [
                    'post' => [
                        'operationId' => 'hello',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => $schema,
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $operation = $openApi->paths['/tasks/hello/run']->post;
        self::assertInstanceOf(Operation::class, $operation);

        $requestSchema = $operation->requestBody->content['application/json']->schema ?? null;
        self::assertInstanceOf(Schema::class, $requestSchema);
        self::assertSame(['arg'], $requestSchema->getExtensions()[CastorSchemaExtensions::ARGUMENTS] ?? null);
        self::assertSame(['name'], $requestSchema->getExtensions()[CastorSchemaExtensions::OPTIONS] ?? null);

        $projectRoot = sys_get_temp_dir() . '/castor-api-test-' . uniqid('', true);
        $openapiDir = $projectRoot . '/.castor/api';
        mkdir($openapiDir, 0o777, true);
        $openapiPath = $openapiDir . '/openapi.json';
        file_put_contents($openapiPath, json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/tasks/hello/run' => [
                    'post' => [
                        'operationId' => 'hello',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => $schema,
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $loader = new OpenApiLoader($openapiPath);
        $context = $loader->match(Request::create('/tasks/hello/run', 'POST'));

        self::assertNotNull($context);
        self::assertSame('hello', $context->taskName);
        self::assertSame(['arg'], $context->requestSchema[CastorSchemaExtensions::ARGUMENTS] ?? null);
        self::assertSame(['name'], $context->requestSchema[CastorSchemaExtensions::OPTIONS] ?? null);
    }
}
