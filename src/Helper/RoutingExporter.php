<?php

declare(strict_types=1);

namespace Castor\Api\Helper;

use Castor\Api\OpenApi\OpenApiExporter;
use Castor\Api\Registry\ApiTaskRegistry;

final class RoutingExporter
{
    public static function writeOpenApi(): string
    {
        return OpenApiExporter::write(Paths::projectRoot());
    }

    public static function countTasks(): int
    {
        return \count(ApiTaskRegistry::all());
    }
}
