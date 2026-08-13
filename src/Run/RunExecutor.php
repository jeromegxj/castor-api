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

        try {
            $result = SubprocessTaskRunner::runWithCliArgs(
                castorBinary: $record->castorBinary,
                projectRoot: $record->projectRoot,
                workingDirectory: $record->workingDirectory,
                taskName: $record->task,
                cliArgs: $record->cliArgs,
            );

            $record->exitCode = $result['exitCode'];
            $record->stdout = $result['stdout'];
            $record->stderr = $result['stderr'];
            $record->durationMs = $result['durationMs'];
            $record->status = 0 === $result['exitCode'] ? RunStatus::Completed : RunStatus::Failed;
        } catch (\Throwable $exception) {
            $record->exitCode = 1;
            $record->stderr = $exception->getMessage();
            $record->status = RunStatus::Failed;
        } finally {
            $record->finishedAt = time();
            $store->save($record);
        }

        return $record;
    }
}
