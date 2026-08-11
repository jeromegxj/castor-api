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
    private const WORKING_DIRECTORY_EXTENSION = 'x-castor-working-directory';

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
            $runPath = self::runPath($endpoint);

            foreach ($endpoint->methods as $method) {
                $operation = [
                    'operationId' => $endpoint->taskName,
                    'description' => $endpoint->description,
                    'responses' => [
                        '200' => [
                            'description' => 'Task execution result',
                            'content' => [
                                'application/json' => [
                                    'schema' => OpenApiSchemaBuilder::taskRunResponseSchema(),
                                ],
                            ],
                        ],
                    ],
                ];

                if (null !== $endpoint->workingDirectory && $endpoint->workingDirectory !== $projectRoot) {
                    $operation[self::WORKING_DIRECTORY_EXTENSION] = $endpoint->workingDirectory;
                }

                if (null !== $endpoint->schema) {
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

                $paths[$runPath][strtolower($method)] = $operation;
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

    private static function runPath(ApiEndpoint $endpoint): string
    {
        if ($endpoint->path !== TaskName::defaultPath($endpoint->taskName)) {
            return rtrim(rawurldecode($endpoint->path), '/') . '/run';
        }

        return \sprintf('/tasks/%s/run', $endpoint->taskName);
    }
}
