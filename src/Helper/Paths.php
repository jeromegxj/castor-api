<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

final class Paths
{
    public static function projectRoot(): string
    {
        $path = getcwd() ?: '/';

        while (!(is_file($path . '/castor.php') || is_file($path . '/.castor/castor.php'))) {
            $parent = \dirname($path);

            if ($parent === $path) {
                return getcwd() ?: '/';
            }

            $path = $parent;
        }

        return $path;
    }

    public static function apiVarDir(): string
    {
        return self::projectRoot() . '/.castor/api';
    }

    public static function openapiPath(): string
    {
        return self::apiVarDir() . '/openapi.json';
    }
}
