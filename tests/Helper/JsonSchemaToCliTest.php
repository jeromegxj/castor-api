<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Helper;

use Jolicode\CastorApi\Helper\JsonSchemaToCli;
use Jolicode\CastorApi\OpenApi\CastorSchemaExtensions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class JsonSchemaToCliTest extends TestCase
{
    #[DataProvider('provideConvertCases')]
    public function testConvert(array $schema, array $payload, array $expected): void
    {
        self::assertSame($expected, JsonSchemaToCli::convert($schema, $payload));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, list<string>}>
     */
    public static function provideConvertCases(): iterable
    {
        yield 'positional argument only' => [
            self::schema(
                properties: ['arg' => ['type' => 'string']],
                arguments: ['arg'],
                options: [],
                required: ['arg'],
            ),
            ['arg' => 'world'],
            ['world'],
        ];

        yield 'option only' => [
            self::schema(
                properties: ['name' => ['type' => 'string']],
                arguments: [],
                options: ['name'],
            ),
            ['name' => 'Castor'],
            ['--name=Castor'],
        ];

        yield 'mixed argument and option' => [
            self::schema(
                properties: [
                    'env' => ['type' => 'string'],
                    'verbose' => ['type' => 'boolean'],
                ],
                arguments: ['env'],
                options: ['verbose'],
                required: ['env'],
            ),
            ['env' => 'prod', 'verbose' => true],
            ['prod', '--verbose'],
        ];

        yield 'array argument expands to positional values' => [
            self::schema(
                properties: ['tags' => ['type' => 'array', 'items' => ['type' => 'string']]],
                arguments: ['tags'],
                options: [],
            ),
            ['tags' => ['a', 'b', 'c']],
            ['a', 'b', 'c'],
        ];

        yield 'boolean option false uses no- prefix' => [
            self::schema(
                properties: ['verbose' => ['type' => 'boolean']],
                arguments: [],
                options: ['verbose'],
            ),
            ['verbose' => false],
            ['--no-verbose'],
        ];

        yield 'array option repeats flag' => [
            self::schema(
                properties: ['tag' => ['type' => 'array', 'items' => ['type' => 'string']]],
                arguments: [],
                options: ['tag'],
            ),
            ['tag' => ['one', 'two']],
            ['--tag=one', '--tag=two'],
        ];

        yield 'optional properties omitted from payload are skipped' => [
            self::schema(
                properties: [
                    'name' => ['type' => 'string'],
                    'verbose' => ['type' => 'boolean'],
                ],
                arguments: [],
                options: ['name', 'verbose'],
            ),
            ['name' => 'Castor'],
            ['--name=Castor'],
        ];

        yield 'empty schema returns empty args' => [
            [],
            [],
            [],
        ];
    }

    public function testNullSchemaReturnsEmptyArgs(): void
    {
        self::assertSame([], JsonSchemaToCli::convert(null, []));
    }

    public function testMissingRequiredPropertyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required property "arg".');

        JsonSchemaToCli::convert(
            self::schema(
                properties: ['arg' => ['type' => 'string']],
                arguments: ['arg'],
                options: [],
                required: ['arg'],
            ),
            [],
        );
    }

    public function testMissingArgumentsExtensionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required schema extension "x-castor-arguments".');

        JsonSchemaToCli::convert(
            [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                CastorSchemaExtensions::OPTIONS => ['name'],
            ],
            ['name' => 'Castor'],
        );
    }

    public function testMissingOptionsExtensionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required schema extension "x-castor-options".');

        JsonSchemaToCli::convert(
            [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                CastorSchemaExtensions::ARGUMENTS => [],
            ],
            ['name' => 'Castor'],
        );
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string>                        $arguments
     * @param list<string>                        $options
     * @param list<string>                        $required
     *
     * @return array<string, mixed>
     */
    private static function schema(
        array $properties,
        array $arguments,
        array $options,
        array $required = [],
    ): array {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
            CastorSchemaExtensions::ARGUMENTS => $arguments,
            CastorSchemaExtensions::OPTIONS => $options,
        ];

        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
