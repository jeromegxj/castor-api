<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

use Jolicode\CastorApi\OpenApi\OpenApiExporter;

final class RoutingExporter
{
    public static function writeOpenApi(): string
    {
        return OpenApiExporter::write(Paths::projectRoot());
    }

    public static function countTasks(): int
    {
        return \count(\Jolicode\CastorApi\Registry\ApiTaskRegistry::all());
    }
}
