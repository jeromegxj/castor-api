<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Schema;
use cebe\openapi\Writer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class OpenApiLoader
{
    private const WORKING_DIRECTORY_EXTENSION = 'x-castor-working-directory';

    private OpenApi $spec;

    private RouteCollection $routes;

    private string $projectRoot;

    private string $castorBinary;

    public function __construct(
        private readonly string $filePath,
    ) {
        $json = file_get_contents($filePath);

        if (false === $json) {
            throw new \RuntimeException(\sprintf('Unable to read OpenAPI spec at "%s".', $filePath));
        }

        $this->spec = Reader::readFromJson($json);
        $this->projectRoot = self::projectRootFromOpenApiPath($filePath);
        $this->castorBinary = self::resolveCastorBinary();
        $this->routes = self::buildRoutes($this->spec);
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getCastorBinary(): string
    {
        return $this->castorBinary;
    }

    public function toJson(): string
    {
        return Writer::writeToJson($this->spec);
    }

    public function match(Request $request): ?OperationContext
    {
        $context = new RequestContext();
        $context->setMethod($request->getMethod());
        $matcher = new UrlMatcher($this->routes, $context);

        try {
            $parameters = $matcher->match($request->getPathInfo());
        } catch (\Throwable) {
            return null;
        }

        $operationId = $parameters['_route'];
        $path = $request->getPathInfo();
        $method = strtoupper($request->getMethod());
        $operation = $this->findOperation($path, strtolower($method));

        if (null === $operation) {
            return null;
        }

        return new OperationContext(
            operationId: $operationId,
            path: $path,
            method: $method,
            requestSchema: self::extractRequestSchema($operation),
            workingDirectory: self::extractWorkingDirectory($operation),
        );
    }

    private function findOperation(string $path, string $method): ?Operation
    {
        if (!isset($this->spec->paths[$path])) {
            return null;
        }

        $pathItem = $this->spec->paths[$path];
        $operation = $pathItem->{$method} ?? null;

        return $operation instanceof Operation ? $operation : null;
    }

    private static function buildRoutes(OpenApi $spec): RouteCollection
    {
        $routes = new RouteCollection();

        foreach ($spec->paths as $path => $pathItem) {
            foreach ($pathItem->getOperations() as $method => $operation) {
                if ('' === $operation->operationId) {
                    continue;
                }

                $routes->add($operation->operationId, new Route($path, methods: [strtoupper($method)]));
            }
        }

        return $routes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function extractRequestSchema(Operation $operation): ?array
    {
        $requestBody = $operation->requestBody;

        if (null === $requestBody || !isset($requestBody->content['application/json'])) {
            return null;
        }

        $schema = $requestBody->content['application/json']->schema ?? null;

        if (!$schema instanceof Schema) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode(json_encode($schema->getSerializableData(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        foreach ($schema->getExtensions() as $name => $value) {
            $data[$name] = $value;
        }

        return $data;
    }

    private static function extractWorkingDirectory(Operation $operation): ?string
    {
        $extensions = $operation->getExtensions();
        $workingDirectory = $extensions[self::WORKING_DIRECTORY_EXTENSION] ?? null;

        return \is_string($workingDirectory) && '' !== $workingDirectory ? $workingDirectory : null;
    }

    public static function projectRootFromOpenApiPath(string $openapiPath): string
    {
        return \dirname($openapiPath, 3);
    }

    private static function resolveCastorBinary(): string
    {
        $binary = getenv('CASTOR_BINARY');

        return \is_string($binary) && '' !== $binary ? $binary : 'castor';
    }
}
