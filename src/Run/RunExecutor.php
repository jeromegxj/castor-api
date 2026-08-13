<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Run;

use Jolicode\CastorApi\Runner\SubprocessTaskRunner;

final class RunExecutor
{
    public function execute(RunRecord $record): RunRecord
    {
        $record->status = RunStatus::Running;
        $record->startedAt = time();

        $store = new RunStore($record->projectRoot);
        $store->save($record);

        $result = SubprocessTaskRunner::runWithCliArgs(
            castorBinary: $record->castorBinary,
            projectRoot: $record->projectRoot,
            workingDirectory: $record->workingDirectory,
            taskName: $record->task,
            cliArgs: $record->cliArgs,
        );

        $record->finishedAt = time();
        $record->exitCode = $result['exitCode'];
        $record->stdout = $result['stdout'];
        $record->stderr = $result['stderr'];
        $record->durationMs = $result['durationMs'];
        $record->status = 0 === $result['exitCode'] ? RunStatus::Completed : RunStatus::Failed;

        $store->save($record);

        return $record;
    }
}
