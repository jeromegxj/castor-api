<?php

declare(strict_types=1);

use Jolicode\CastorApi\Http\Router;

/**
 * @return bool whether Castor API classes are available
 */
function castor_api_load_autoload(?string $openapiPath = null, ?string $packageRoot = null): bool
{
    if (class_exists(Router::class)) {
        return true;
    }

    $openapiPath ??= getenv('CASTOR_API_OPENAPI') ?: null;
    $packageRoot ??= getenv('CASTOR_API_PACKAGE_ROOT') ?: \dirname(__DIR__);

    $candidates = [];

    if ('' !== $packageRoot) {
        $candidates[] = $packageRoot . '/vendor/autoload.php';
    }

    if (\is_string($openapiPath) && '' !== $openapiPath) {
        $consumerAutoload = \dirname($openapiPath, 2) . '/vendor/autoload.php';
        $packageCastorAutoload = $packageRoot . '/.castor/vendor/autoload.php';

        if ($consumerAutoload !== $packageCastorAutoload) {
            $candidates[] = $consumerAutoload;
        }
    }

    if ('' !== $packageRoot) {
        $candidates[] = $packageRoot . '/.castor/vendor/autoload.php';
    }

    foreach (array_unique($candidates) as $autoload) {
        if (!castor_api_is_usable_autoload($autoload)) {
            continue;
        }

        require_once $autoload;

        if (class_exists(Router::class)) {
            return true;
        }
    }

    return false;
}

function castor_api_is_usable_autoload(string $autoload): bool
{
    if (!is_file($autoload)) {
        return false;
    }

    $vendorDir = \dirname($autoload);

    return is_file($vendorDir . '/symfony/http-foundation/Response.php')
        && is_file($vendorDir . '/symfony/routing/Route.php');
}
