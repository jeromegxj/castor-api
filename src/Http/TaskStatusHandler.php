<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Http;

use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Jolicode\CastorApi\OpenApi\OperationContext;
use Jolicode\CastorApi\Run\RunStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TaskStatusHandler
{
    public static function status(OpenApiLoader $loader, OperationContext $operation, Request $request): JsonResponse
    {
        if ('GET' !== $request->getMethod()) {
            return new JsonResponse(['error' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $runId = $operation->runId;

        if (null === $runId || '' === $runId) {
            return new JsonResponse(['error' => 'Missing run identifier.'], Response::HTTP_BAD_REQUEST);
        }

        $record = new RunStore($loader->getProjectRoot())->get($runId);

        if (null === $record) {
            return new JsonResponse(['error' => 'Run not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($record->task !== $operation->taskName) {
            return new JsonResponse(['error' => 'Run not found.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            $record->toStatusResponse(),
            TaskHttpStatus::fromRunStatus($record->status),
        );
    }
}
