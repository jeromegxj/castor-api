<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Registry;

use Jolicode\CastorApi\Attribute\AsApi;
use Jolicode\CastorApi\Descriptor\ApiEndpoint;
use Jolicode\CastorApi\Helper\TaskName;

final class ApiTaskRegistry
{
    /** @var array<string, ApiEndpoint> */
    private static array $endpoints = [];

    /**
     * @param array{arguments: list<array<string, mixed>>, options: list<array<string, mixed>>}|null $schema
     */
    public static function registerEndpoint(
        string $taskName,
        AsApi $api,
        string $description,
        ?string $workingDirectory,
        ?array $schema = null,
    ): void {
        self::$endpoints[$taskName] = new ApiEndpoint(
            taskName: $taskName,
            path: TaskName::pathFromApi($api, $taskName),
            methods: $api->methods,
            description: $description,
            workingDirectory: $workingDirectory,
            async: $api->async,
            schema: $schema,
        );
    }

    /**
     * @return array<string, ApiEndpoint>
     */
    public static function all(): array
    {
        return self::$endpoints;
    }

    public static function reset(): void
    {
        self::$endpoints = [];
    }
}
