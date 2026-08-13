<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\OpenApi;

final class OperationContext
{
    /**
     * @param array<string, mixed>|null $requestSchema
     */
    public function __construct(
        public readonly string $operationId,
        public readonly string $taskName,
        public readonly OperationKind $kind,
        public readonly string $path,
        public readonly string $method,
        public readonly ?array $requestSchema,
        public readonly ?string $workingDirectory,
        public readonly ?string $runId = null,
    ) {
    }

    public function isHealthCheck(): bool
    {
        return OperationKind::Health === $this->kind;
    }

    public function isTaskRun(): bool
    {
        return OperationKind::Run === $this->kind;
    }

    public function isTaskStart(): bool
    {
        return OperationKind::Start === $this->kind;
    }

    public function isTaskStatus(): bool
    {
        return OperationKind::Status === $this->kind;
    }
}
