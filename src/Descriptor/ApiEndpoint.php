<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Descriptor;

final class ApiEndpoint
{
    /**
     * @param list<string>              $methods
     * @param array<string, mixed>|null $schema
     */
    public function __construct(
        public readonly string $taskName,
        public readonly string $path,
        public readonly array $methods,
        public readonly string $description,
        public readonly ?string $workingDirectory,
        public readonly bool $async,
        public ?array $schema = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includeSchema = true): array
    {
        $data = [
            'name' => $this->taskName,
            'path' => $this->path,
            'methods' => $this->methods,
            'description' => $this->description,
            'workingDirectory' => $this->workingDirectory,
            'async' => $this->async,
        ];

        if ($includeSchema && null !== $this->schema) {
            $data['schema'] = $this->schema;
        }

        return $data;
    }
}
