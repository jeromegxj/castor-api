<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Http;

use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Jolicode\CastorApi\OpenApi\OperationContext;
use Jolicode\CastorApi\Runner\SubprocessTaskRunner;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TaskRunHandler
{
    public static function run(OpenApiLoader $loader, OperationContext $operation, Request $request): JsonResponse
    {
        if ($request->getMethod() !== $operation->method) {
            return new JsonResponse(['error' => 'Method not allowed.'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        try {
            $payload = RequestPayloadParser::parse($request);
            $result = SubprocessTaskRunner::run(
                castorBinary: $loader->getCastorBinary(),
                projectRoot: $loader->getProjectRoot(),
                workingDirectory: $operation->workingDirectory,
                taskName: $operation->taskName,
                requestSchema: $operation->requestSchema,
                payload: $payload,
            );
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'task' => $operation->taskName,
            'exitCode' => $result['exitCode'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'durationMs' => $result['durationMs'],
        ], TaskHttpStatus::fromExitCode($result['exitCode']));
    }
}
