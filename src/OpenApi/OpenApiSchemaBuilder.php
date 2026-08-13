<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\OpenApi;

final class OpenApiSchemaBuilder
{
    /**
     * @param array{arguments: list<array<string, mixed>>, options: list<array<string, mixed>>} $castorSchema
     *
     * @return array<string, mixed>
     */
    public static function fromCastorSchema(array $castorSchema): array
    {
        $properties = [];
        $required = [];

        foreach ($castorSchema['arguments'] as $argument) {
            $name = (string) $argument['name'];
            $properties[$name] = self::propertyFromArgument($argument);

            if (($argument['required'] ?? false) === true) {
                $required[] = $name;
            }
        }

        foreach ($castorSchema['options'] as $option) {
            $name = (string) $option['name'];
            $properties[$name] = self::propertyFromOption($option);

            if (($option['isValueRequired'] ?? false) === true) {
                $required[] = $name;
            }
        }

        $argumentNames = array_map(
            static fn (array $argument): string => (string) $argument['name'],
            $castorSchema['arguments'],
        );

        $optionNames = array_map(
            static fn (array $option): string => (string) $option['name'],
            $castorSchema['options'],
        );

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            CastorSchemaExtensions::ARGUMENTS => $argumentNames,
            CastorSchemaExtensions::OPTIONS => $optionNames,
        ];

        if ([] !== $required) {
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $argument
     *
     * @return array<string, mixed>
     */
    private static function propertyFromArgument(array $argument): array
    {
        $property = [
            'description' => (string) ($argument['description'] ?? ''),
        ];

        if (($argument['isArray'] ?? false) === true) {
            $property['type'] = 'array';
            $property['items'] = ['type' => 'string'];
        } else {
            $property['type'] = 'string';
        }

        if (\array_key_exists('default', $argument) && null !== $argument['default']) {
            $property['default'] = $argument['default'];
        }

        return $property;
    }

    /**
     * @param array<string, mixed> $option
     *
     * @return array<string, mixed>
     */
    private static function propertyFromOption(array $option): array
    {
        $property = [
            'description' => (string) ($option['description'] ?? ''),
        ];

        if (($option['isFlag'] ?? false) === true) {
            $property['type'] = 'boolean';

            if (\array_key_exists('default', $option)) {
                $property['default'] = (bool) $option['default'];
            }

            return $property;
        }

        if (($option['isArray'] ?? false) === true) {
            $property['type'] = 'array';
            $property['items'] = ['type' => 'string'];
        } else {
            $property['type'] = 'string';
        }

        if (\array_key_exists('default', $option) && null !== $option['default']) {
            $property['default'] = $option['default'];
        }

        return $property;
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskRunResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task' => ['type' => 'string'],
                'exitCode' => ['type' => 'integer'],
                'stdout' => ['type' => 'string'],
                'stderr' => ['type' => 'string'],
                'durationMs' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskStartResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'task' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['pending']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskStatusResponseSchema(): array
    {
        return self::taskExecutionResponseSchema(includeId: true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function taskExecutionResponseSchema(bool $includeId): array
    {
        $properties = [
            'task' => ['type' => 'string'],
            'status' => [
                'type' => 'string',
                'enum' => ['pending', 'running', 'completed', 'failed'],
            ],
            'exitCode' => ['type' => ['integer', 'null']],
            'stdout' => ['type' => ['string', 'null']],
            'stderr' => ['type' => ['string', 'null']],
            'durationMs' => ['type' => ['integer', 'null']],
        ];

        if ($includeId) {
            $properties = ['id' => ['type' => 'string']] + $properties;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }
}
