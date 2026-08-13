<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Run;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
