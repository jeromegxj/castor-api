<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\OpenApi;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\Writer;
use Jolicode\CastorApi\Descriptor\ApiEndpoint;
use Jolicode\CastorApi\Helper\Paths;
use Jolicode\CastorApi\Helper\TaskName;
use Jolicode\CastorApi\Registry\ApiTaskRegistry;

final class OpenApiExporter
{
    public static function build(string $projectRoot): OpenApi
    {
        $paths = [
            '/health' => [
                'get' => [
                    'operationId' => 'castor.health',
                    'responses' => [
                        '200' => [
                            'description' => 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $endpoints = ApiTaskRegistry::all();
        uasort($endpoints, static fn (ApiEndpoint $a, ApiEndpoint $b): int => strcmp($a->taskName, $b->taskName));

        foreach ($endpoints as $endpoint) {
            foreach ($endpoint->methods as $method) {
                $paths[self::runPath($endpoint)][strtolower($method)] = self::buildRunOperation($endpoint, $projectRoot);
            }

            if ($endpoint->async) {
                $paths[self::startPath($endpoint)]['post'] = self::buildStartOperation($endpoint, $projectRoot);
                $paths[self::statusPath($endpoint)]['get'] = self::buildStatusOperation($endpoint, $projectRoot);
            }
        }

        return new OpenApi([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Castor API',
                'version' => '1.0.0',
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => $paths,
        ]);
    }

    public static function write(string $projectRoot): string
    {
        $openapiPath = Paths::openapiPath();
        $dir = \dirname($openapiPath);

        if (!is_dir($dir) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Unable to create directory "%s".', $dir));
        }

        Writer::writeToJsonFile(self::build($projectRoot), $openapiPath);

        return $openapiPath;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildRunOperation(ApiEndpoint $endpoint, string $projectRoot): array
    {
        $responseSchema = OpenApiSchemaBuilder::taskRunResponseSchema();

        return self::addTaskFailureResponse(
            self::buildTaskOperation(
                endpoint: $endpoint,
                projectRoot: $projectRoot,
                operationId: $endpoint->taskName,
                description: $endpoint->description,
                responseSchema: $responseSchema,
                responseDescription: 'Task execution result',
                responseCode: '200',
            ),
            $responseSchema,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildStartOperation(ApiEndpoint $endpoint, string $projectRoot): array
    {
        return self::buildTaskOperation(
            endpoint: $endpoint,
            projectRoot: $projectRoot,
            operationId: $endpoint->taskName . '.start',
            description: $endpoint->description . ' (async start)',
            responseSchema: OpenApiSchemaBuilder::taskStartResponseSchema(),
            responseDescription: 'Async run accepted',
            responseCode: '202',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildStatusOperation(ApiEndpoint $endpoint, string $projectRoot): array
    {
        $responseSchema = OpenApiSchemaBuilder::taskStatusResponseSchema();

        $operation = self::addTaskFailureResponse(
            self::buildTaskOperation(
                endpoint: $endpoint,
                projectRoot: $projectRoot,
                operationId: $endpoint->taskName . '.status',
                description: $endpoint->description . ' (async status)',
                responseSchema: $responseSchema,
                responseDescription: 'Async run status',
                responseCode: '200',
                includeRequestBody: false,
            ),
            $responseSchema,
        );

        $operation['parameters'] = [[
            'name' => 'runId',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ]];

        return $operation;
    }

    /**
     * @param array<string, mixed> $responseSchema
     *
     * @return array<string, mixed>
     */
    private static function buildTaskOperation(
        ApiEndpoint $endpoint,
        string $projectRoot,
        string $operationId,
        string $description,
        array $responseSchema,
        string $responseDescription,
        string $responseCode,
        bool $includeRequestBody = true,
    ): array {
        $operation = [
            'operationId' => $operationId,
            'description' => $description,
            'responses' => [
                $responseCode => [
                    'description' => $responseDescription,
                    'content' => [
                        'application/json' => [
                            'schema' => $responseSchema,
                        ],
                    ],
                ],
            ],
        ];

        if (null !== $endpoint->workingDirectory && $endpoint->workingDirectory !== $projectRoot) {
            $operation[CastorSchemaExtensions::WORKING_DIRECTORY] = $endpoint->workingDirectory;
        }

        if ($includeRequestBody && null !== $endpoint->schema) {
            /** @var array{arguments: list<array<string, mixed>>, options: list<array<string, mixed>>} $castorSchema */
            $castorSchema = $endpoint->schema;

            $operation['requestBody'] = [
                'required' => false,
                'content' => [
                    'application/json' => [
                        'schema' => OpenApiSchemaBuilder::fromCastorSchema($castorSchema),
                    ],
                ],
            ];
        }

        return $operation;
    }

    /**
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function addTaskFailureResponse(array $operation, array $schema): array
    {
        $operation['responses']['422'] = [
            'description' => 'Task execution failed',
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];

        return $operation;
    }

    private static function runPath(ApiEndpoint $endpoint): string
    {
        return TaskName::actionPath($endpoint->path, 'run');
    }

    private static function startPath(ApiEndpoint $endpoint): string
    {
        return TaskName::actionPath($endpoint->path, 'start');
    }

    private static function statusPath(ApiEndpoint $endpoint): string
    {
        return TaskName::statusPathFromBase($endpoint->path);
    }
}
