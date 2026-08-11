<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

use Jolicode\CastorApi\OpenApi\CastorSchemaExtensions;

final class JsonSchemaToCli
{
    /**
     * @param array<string, mixed>|null $schema
     * @param array<string, mixed>      $payload
     *
     * @return list<string>
     */
    public static function convert(?array $schema, array $payload): array
    {
        if (null === $schema || [] === $schema) {
            return [];
        }

        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        if (!\is_array($properties)) {
            return [];
        }

        if (!\is_array($required)) {
            $required = [];
        }

        $argumentNames = self::requireExtensionList($schema, CastorSchemaExtensions::ARGUMENTS);
        $optionNames = self::requireExtensionList($schema, CastorSchemaExtensions::OPTIONS);

        $args = [];

        foreach ($argumentNames as $name) {
            $property = $properties[$name] ?? null;

            if (!\is_array($property)) {
                throw new \InvalidArgumentException(\sprintf('Unknown argument property "%s" in schema.', $name));
            }

            if (!\array_key_exists($name, $payload)) {
                if (\in_array($name, $required, true)) {
                    throw new \InvalidArgumentException(\sprintf('Missing required property "%s".', $name));
                }

                continue;
            }

            $value = $payload[$name];
            $type = $property['type'] ?? 'string';

            if ('array' === $type && \is_array($value)) {
                foreach ($value as $item) {
                    $args[] = (string) $item;
                }

                continue;
            }

            $args[] = (string) $value;
        }

        foreach ($optionNames as $name) {
            $property = $properties[$name] ?? null;

            if (!\is_array($property)) {
                throw new \InvalidArgumentException(\sprintf('Unknown option property "%s" in schema.', $name));
            }

            if (!\array_key_exists($name, $payload)) {
                if (\in_array($name, $required, true)) {
                    throw new \InvalidArgumentException(\sprintf('Missing required property "%s".', $name));
                }

                continue;
            }

            $value = $payload[$name];
            $type = $property['type'] ?? 'string';

            if ('boolean' === $type) {
                $args[] = $value ? '--' . $name : '--no-' . $name;

                continue;
            }

            if ('array' === $type && \is_array($value)) {
                foreach ($value as $item) {
                    $args[] = \sprintf('--%s=%s', $name, $item);
                }

                continue;
            }

            $args[] = \sprintf('--%s=%s', $name, $value);
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<string>
     */
    private static function requireExtensionList(array $schema, string $extension): array
    {
        if (!\array_key_exists($extension, $schema)) {
            throw new \InvalidArgumentException(\sprintf('Missing required schema extension "%s". Re-export the OpenAPI spec with "castor api:export-openapi".', $extension));
        }

        $names = $schema[$extension];

        if (!\is_array($names)) {
            throw new \InvalidArgumentException(\sprintf('Schema extension "%s" must be a list of property names.', $extension));
        }

        $result = [];

        foreach ($names as $name) {
            if (!\is_string($name)) {
                throw new \InvalidArgumentException(\sprintf('Schema extension "%s" must contain only strings.', $extension));
            }

            $result[] = $name;
        }

        return $result;
    }
}
