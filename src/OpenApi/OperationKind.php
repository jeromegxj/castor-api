<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\OpenApi;

enum OperationKind
{
    case Health;
    case Run;
    case Start;
    case Status;

    public static function fromOperationId(string $operationId): self
    {
        if ('castor.health' === $operationId) {
            return self::Health;
        }

        if (str_ends_with($operationId, '.start')) {
            return self::Start;
        }

        if (str_ends_with($operationId, '.status')) {
            return self::Status;
        }

        return self::Run;
    }

    public static function taskNameFromOperationId(string $operationId): string
    {
        return match (self::fromOperationId($operationId)) {
            self::Start => substr($operationId, 0, -\strlen('.start')),
            self::Status => substr($operationId, 0, -\strlen('.status')),
            default => $operationId,
        };
    }
}
