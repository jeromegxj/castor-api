<?php

declare(strict_types=1);

namespace Castor\Api\Run;

final class RunConflictException extends \RuntimeException
{
    public function __construct(
        public readonly string $task,
        public readonly RunRecord $conflict,
        public readonly string $lock,
    ) {
        parent::__construct(\sprintf(
            'Cannot start "%s": "%s" (run %s) is still %s and holds the lock "%s".',
            $task,
            $conflict->task,
            $conflict->id,
            $conflict->status->value,
            $lock,
        ));
    }
}
