<?php

declare(strict_types=1);

namespace Castor\Api\Http;

use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\OpenApi\OperationContext;
use Castor\Api\Run\RunStore;
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

        $store = new RunStore($loader->getProjectRoot());
        $record = $store->get($runId);

        if (null === $record) {
            return new JsonResponse(['error' => 'Run not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($record->task !== $operation->taskName) {
            return new JsonResponse(['error' => 'Run not found.'], Response::HTTP_NOT_FOUND);
        }

        $record = $store->failIfLost($record);

        return new JsonResponse(
            $record->toStatusResponse(),
            TaskHttpStatus::fromRunStatus($record->status),
        );
    }
}
