<?php

declare(strict_types=1);

namespace castor_api\task;

use Castor\Attribute\AsTask;
use Jolicode\CastorApi\Helper\RoutingExporter;

use function Castor\io;

#[AsTask(name: 'export-openapi', namespace: 'api', description: 'Export #[AsApi] tasks as OpenAPI spec to .castor/api/openapi.json')]
function export_openapi(): void
{
    if (0 === RoutingExporter::countTasks()) {
        io()->warning('No tasks marked with #[AsApi] were found.');
    }

    $openapiPath = RoutingExporter::writeOpenApi();

    io()->success(\sprintf(
        'Castor API OpenAPI spec exported to %s (%d task(s) exposed)',
        $openapiPath,
        RoutingExporter::countTasks(),
    ));
}
