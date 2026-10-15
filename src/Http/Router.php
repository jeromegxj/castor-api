<?php

declare(strict_types=1);

namespace Castor\Api\Http;

use Castor\Api\Helper\AuthToken;
use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\OpenApi\OperationKind;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Router
{
    public function handle(Request $request, OpenApiLoader $loader, string $packageRoot): Response
    {
        $authResponse = AuthMiddleware::authenticate($request, AuthToken::resolve());

        if ($authResponse instanceof Response) {
            return $authResponse;
        }

        if ('/openapi.json' === $request->getPathInfo() && 'GET' === $request->getMethod()) {
            return new JsonResponse(json_decode($loader->toJson(), true));
        }

        // Served without the spec, so it also works with an OpenAPI file exported by an older version.
        if ('/runs' === $request->getPathInfo() && 'GET' === $request->getMethod()) {
            return RunListHandler::list($loader);
        }

        $operation = $loader->match($request);

        if (null === $operation) {
            return new JsonResponse(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        return match ($operation->kind) {
            OperationKind::Health => new JsonResponse(['status' => 'ok']),
            OperationKind::Start => TaskStartHandler::start($loader, $operation, $request, $packageRoot),
            OperationKind::Status => TaskStatusHandler::status($loader, $operation, $request),
            OperationKind::Run => TaskRunHandler::run($loader, $operation, $request),
        };
    }
}
