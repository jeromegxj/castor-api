<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Http;

use Jolicode\CastorApi\Run\RunStatus;
use Symfony\Component\HttpFoundation\Response;

final class TaskHttpStatus
{
    public static function fromExitCode(int $exitCode): int
    {
        return 0 === $exitCode ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public static function fromRunStatus(RunStatus $status): int
    {
        return RunStatus::Failed === $status ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;
    }
}
