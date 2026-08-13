<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Run;

use Jolicode\CastorApi\Helper\Paths;
use Symfony\Component\Uid\Uuid;

final class RunStore
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param list<string> $cliArgs
     */
    public function create(
        string $task,
        array $cliArgs,
        string $castorBinary,
        ?string $workingDirectory,
    ): RunRecord {
        $record = new RunRecord(
            id: Uuid::v4()->toRfc4122(),
            task: $task,
            status: RunStatus::Pending,
            cliArgs: $cliArgs,
            projectRoot: $this->projectRoot,
            castorBinary: $castorBinary,
            workingDirectory: $workingDirectory,
            createdAt: time(),
        );

        $this->save($record);

        return $record;
    }

    public function get(string $runId): ?RunRecord
    {
        $path = $this->pathFor($runId);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return RunRecord::fromStorage($data);
    }

    public function save(RunRecord $record): void
    {
        $directory = Paths::runsDir($this->projectRoot);

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Unable to create runs directory "%s".', $directory));
        }

        $path = $this->pathFor($record->id);
        $payload = json_encode($record->toStorage(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        if (false === file_put_contents($path, $payload, LOCK_EX)) {
            throw new \RuntimeException(\sprintf('Unable to write run record "%s".', $path));
        }
    }

    private function pathFor(string $runId): string
    {
        return Paths::runsDir($this->projectRoot) . '/' . $runId . '.json';
    }
}
