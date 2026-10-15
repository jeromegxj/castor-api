<?php

declare(strict_types=1);

namespace Castor\Api\Http;

use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\Run\RunRecord;
use Castor\Api\Run\RunStore;
use Symfony\Component\HttpFoundation\JsonResponse;

final class RunListHandler
{
    public static function list(OpenApiLoader $loader): JsonResponse
    {
        $runs = new RunStore($loader->getProjectRoot())->active();

        return new JsonResponse([
            'runs' => array_map(static fn (RunRecord $run): array => $run->toSummaryResponse(), $runs),
        ]);
    }
}
