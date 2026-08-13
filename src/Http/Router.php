<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Http;

use Jolicode\CastorApi\Helper\AuthToken;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
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

        $operation = $loader->match($request);

        if (null === $operation) {
            return new JsonResponse(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($operation->isHealthCheck()) {
            return new JsonResponse(['status' => 'ok']);
        }

        if ($operation->isTaskStart()) {
            return TaskStartHandler::start($loader, $operation, $request, $packageRoot);
        }

        if ($operation->isTaskStatus()) {
            return TaskStatusHandler::status($loader, $operation, $request);
        }

        return TaskRunHandler::run($loader, $operation, $request);
    }
}
