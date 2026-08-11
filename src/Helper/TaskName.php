<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

use Castor\Descriptor\TaskDescriptor;
use Jolicode\CastorApi\Attribute\AsApi;

final class TaskName
{
    public static function fromDescriptor(TaskDescriptor $descriptor): string
    {
        $attribute = $descriptor->taskAttribute;
        $name = '' !== $attribute->name ? $attribute->name : $descriptor->function->getName();

        if (null !== $attribute->namespace && '' !== $attribute->namespace) {
            return $attribute->namespace . ':' . $name;
        }

        return $name;
    }

    public static function defaultPath(string $taskName): string
    {
        return '/tasks/' . rawurlencode($taskName);
    }

    public static function runPath(string $taskName): string
    {
        return self::defaultPath($taskName) . '/run';
    }

    public static function pathFromApi(AsApi $api, string $taskName): string
    {
        return $api->path ?? self::defaultPath($taskName);
    }
}
