<?php

declare(strict_types=1);

namespace Castor\Api\Run;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
