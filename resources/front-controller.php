<?php

declare(strict_types=1);

error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);

use Jolicode\CastorApi\Http\Router;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/autoload.php';

$openapiPath = getenv('CASTOR_API_OPENAPI') ?: null;
$packageRoot = getenv('CASTOR_API_PACKAGE_ROOT') ?: \dirname(__DIR__);

if (!castor_api_load_autoload(
    \is_string($openapiPath) ? $openapiPath : null,
    $packageRoot,
)) {
    if ('cli-server' !== \PHP_SAPI) {
        header('Content-Type: application/json');
        http_response_code(500);
    }

    echo json_encode([
        'error' => 'Unable to load Castor API classes. Ensure castor-api is installed (castor composer require jolicode/castor-api) or run composer install in the package root.',
    ], JSON_THROW_ON_ERROR);

    exit(1);
}

if (null === $openapiPath || !is_file($openapiPath)) {
    if ('cli-server' !== \PHP_SAPI) {
        header('Content-Type: application/json');
        http_response_code(503);
    }

    echo json_encode([
        'error' => 'OpenAPI spec not found. Run castor api:export-openapi before serving the API.',
    ], JSON_THROW_ON_ERROR);

    exit(1);
}

$loader = new OpenApiLoader($openapiPath);
$router = new Router();
$response = $router->handle(Request::createFromGlobals(), $loader);
$response->send();
