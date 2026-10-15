<?php

declare(strict_types=1);

namespace Castor\Api\Run;

use Castor\Api\Helper\Paths;
use Symfony\Component\Uid\Uuid;

final class RunStore
{
    public const RETENTION_SECONDS = 86_400;

    /**
     * A run still pending after this delay never had a worker pick it up.
     */
    public const PENDING_TIMEOUT_SECONDS = 120;

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
        $this->purgeExpired();

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

    public function purgeExpired(?int $now = null): int
    {
        $directory = Paths::runsDir($this->projectRoot);

        if (!is_dir($directory)) {
            return 0;
        }

        $now ??= time();
        $threshold = $now - self::RETENTION_SECONDS;
        $deleted = 0;

        foreach (glob($directory . '/*.json') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $createdAt = $this->readCreatedAt($path);

            if ($createdAt < $threshold && unlink($path)) {
                ++$deleted;

                $log = substr($path, 0, -\strlen('.json')) . '.log';

                if (is_file($log)) {
                    unlink($log);
                }
            }
        }

        return $deleted;
    }

    public function save(RunRecord $record): void
    {
        $directory = Paths::runsDir($this->projectRoot);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Unable to create runs directory "%s".', $directory));
        }

        $path = $this->pathFor($record->id);
        $payload = json_encode($record->toStorage(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        if (false === file_put_contents($path, $payload, LOCK_EX)) {
            throw new \RuntimeException(\sprintf('Unable to write run record "%s".', $path));
        }
    }

    /**
     * Marks as failed a run whose worker died (killed, crashed, host rebooted)
     * or never started, so pollers do not wait forever.
     *
     * @param callable(int): bool|null $isProcessAlive
     */
    public function failIfLost(RunRecord $record, ?int $now = null, ?callable $isProcessAlive = null): RunRecord
    {
        $now ??= time();
        $isProcessAlive ??= self::isProcessAlive(...);

        $reason = match (true) {
            RunStatus::Running === $record->status && null !== $record->pid && !$isProcessAlive($record->pid) => 'The worker process exited before reporting a result.',
            RunStatus::Pending === $record->status && $now - $record->createdAt > self::PENDING_TIMEOUT_SECONDS => 'The worker process never started.',
            default => null,
        };

        if (null === $reason) {
            return $record;
        }

        // The worker may have saved its result in the meantime.
        $fresh = $this->get($record->id) ?? $record;

        if ($fresh->status !== $record->status) {
            return $fresh;
        }

        $log = self::logPathFor($this->projectRoot, $record->id);
        $logContents = is_file($log) ? trim((string) file_get_contents($log)) : '';

        $fresh->status = RunStatus::Failed;
        $fresh->exitCode ??= 1;
        $fresh->finishedAt = $now;
        $fresh->stderr = trim(($fresh->stderr ?? '') . "\n" . $reason . ('' !== $logContents ? "\n" . $logContents : ''));
        $this->save($fresh);

        return $fresh;
    }

    public static function logPathFor(string $projectRoot, string $runId): string
    {
        return Paths::runsDir($projectRoot) . '/' . $runId . '.log';
    }

    public static function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (\function_exists('posix_kill')) {
            // EPERM means the process exists but belongs to another user.
            return posix_kill($pid, 0) || 1 === posix_get_last_error();
        }

        if (is_dir('/proc/self')) {
            return is_dir('/proc/' . $pid);
        }

        // No way to know: assume the worker is still running.
        return true;
    }

    private function pathFor(string $runId): string
    {
        return Paths::runsDir($this->projectRoot) . '/' . $runId . '.json';
    }

    private function readCreatedAt(string $path): int
    {
        $contents = file_get_contents($path);

        if (false === $contents) {
            return (int) filemtime($path);
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return (int) filemtime($path);
        }

        return isset($data['createdAt']) ? (int) $data['createdAt'] : (int) filemtime($path);
    }
}
