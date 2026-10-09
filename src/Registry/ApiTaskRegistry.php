<?php

declare(strict_types=1);

namespace Castor\Api\Registry;

use Castor\Api\Attribute\AsApi;
use Castor\Api\Descriptor\ApiEndpoint;
use Castor\Api\Helper\TaskName;

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

    public static function get(string $taskName): ?ApiEndpoint
    {
        return self::$endpoints[$taskName] ?? null;
    }

    public static function reset(): void
    {
        self::$endpoints = [];
    }
}
