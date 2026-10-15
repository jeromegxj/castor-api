<?php

declare(strict_types=1);

namespace Castor\Api\Http;

use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\OpenApi\OperationContext;
use Castor\Api\Run\RunConflictException;
use Castor\Api\Runner\AsyncRunLauncher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TaskStartHandler
{
    public static function start(OpenApiLoader $loader, OperationContext $operation, Request $request, string $packageRoot): JsonResponse
    {
        if ('POST' !== $request->getMethod()) {
            return new JsonResponse(['error' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        try {
            $payload = RequestPayloadParser::parse($request);
            $record = new AsyncRunLauncher($loader, $packageRoot)->start($operation, $payload);
        } catch (RunConflictException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'conflict' => $exception->conflict->toSummaryResponse(),
            ], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse($record->toStartResponse(), Response::HTTP_ACCEPTED);
    }
}
