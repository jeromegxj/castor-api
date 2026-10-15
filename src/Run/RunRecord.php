<?php

declare(strict_types=1);

namespace Castor\Api\Run;

final class RunRecord
{
    /**
     * @param list<string>         $cliArgs
     * @param list<string>         $locks
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $id,
        public readonly string $task,
        public RunStatus $status,
        public readonly array $cliArgs,
        public readonly string $projectRoot,
        public readonly string $castorBinary,
        public readonly ?string $workingDirectory,
        public readonly int $createdAt,
        public ?int $startedAt = null,
        public ?int $finishedAt = null,
        public ?int $exitCode = null,
        public ?string $stdout = null,
        public ?string $stderr = null,
        public ?int $durationMs = null,
        public ?int $pid = null,
        public readonly array $locks = [],
        public readonly array $payload = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorage(): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task,
            'status' => $this->status->value,
            'cliArgs' => $this->cliArgs,
            'projectRoot' => $this->projectRoot,
            'castorBinary' => $this->castorBinary,
            'workingDirectory' => $this->workingDirectory,
            'createdAt' => $this->createdAt,
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'exitCode' => $this->exitCode,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'durationMs' => $this->durationMs,
            'pid' => $this->pid,
            'locks' => $this->locks,
            'payload' => $this->payload,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromStorage(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            task: (string) $data['task'],
            status: RunStatus::from((string) $data['status']),
            cliArgs: array_values(array_map(strval(...), $data['cliArgs'] ?? [])),
            projectRoot: (string) $data['projectRoot'],
            castorBinary: (string) $data['castorBinary'],
            workingDirectory: isset($data['workingDirectory']) && \is_string($data['workingDirectory']) ? $data['workingDirectory'] : null,
            createdAt: (int) $data['createdAt'],
            startedAt: isset($data['startedAt']) ? (int) $data['startedAt'] : null,
            finishedAt: isset($data['finishedAt']) ? (int) $data['finishedAt'] : null,
            exitCode: isset($data['exitCode']) ? (int) $data['exitCode'] : null,
            stdout: isset($data['stdout']) && \is_string($data['stdout']) ? $data['stdout'] : null,
            stderr: isset($data['stderr']) && \is_string($data['stderr']) ? $data['stderr'] : null,
            durationMs: isset($data['durationMs']) ? (int) $data['durationMs'] : null,
            pid: isset($data['pid']) ? (int) $data['pid'] : null,
            locks: array_values(array_map(strval(...), \is_array($data['locks'] ?? null) ? $data['locks'] : [])),
            payload: \is_array($data['payload'] ?? null) ? $data['payload'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toStartResponse(): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task,
            'status' => $this->status->value,
        ];
    }

    public function isActive(): bool
    {
        return \in_array($this->status, [RunStatus::Pending, RunStatus::Running], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryResponse(): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task,
            'status' => $this->status->value,
            'locks' => $this->locks,
            'payload' => $this->payload,
            'createdAt' => $this->createdAt,
            'startedAt' => $this->startedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toStatusResponse(): array
    {
        return [
            'id' => $this->id,
            'task' => $this->task,
            'status' => $this->status->value,
            'exitCode' => $this->exitCode,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'durationMs' => $this->durationMs,
        ];
    }
}
