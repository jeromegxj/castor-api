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
        public readonly string $path,
        public readonly string $method,
        public readonly ?array $requestSchema,
        public readonly ?string $workingDirectory,
    ) {
    }

    public function isHealthCheck(): bool
    {
        return 'castor.health' === $this->operationId;
    }

    public function isTaskRun(): bool
    {
        return !$this->isHealthCheck();
    }
}
