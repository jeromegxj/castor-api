<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

use Jolicode\CastorApi\Attribute\AsApi;

final class TaskName
{
    public static function defaultPath(string $taskName): string
    {
        return '/tasks/' . rawurlencode($taskName);
    }

    public static function actionPath(string $basePath, string $action): string
    {
        if (str_starts_with($basePath, '/tasks/')) {
            return $basePath . '/' . $action;
        }

        return rtrim(rawurldecode($basePath), '/') . '/' . $action;
    }

    public static function statusPathFromBase(string $basePath): string
    {
        return self::actionPath($basePath, 'status/{runId}');
    }

    public static function pathFromApi(AsApi $api, string $taskName): string
    {
        return $api->path ?? self::defaultPath($taskName);
    }
}
