<?php

declare(strict_types=1);

namespace Castor\Api\Event;

final readonly class ApiTaskRunEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $taskName,
        public array $payload,
        public ?int $exitCode = null,
        public ?string $stdout = null,
        public ?string $stderr = null,
    ) {
    }
}
