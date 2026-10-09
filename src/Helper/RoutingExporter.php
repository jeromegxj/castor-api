<?php

declare(strict_types=1);

namespace Castor\Api\Helper;

use Castor\Api\OpenApi\OpenApiExporter;

final class RoutingExporter
{
    public static function writeOpenApi(): string
    {
        return OpenApiExporter::write(Paths::projectRoot());
    }

    public static function countTasks(): int
    {
        return \count(\Castor\Api\Registry\ApiTaskRegistry::all());
    }
}
