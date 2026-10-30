<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Registry;

use Castor\Api\Attribute\AsApi;
use Castor\Api\Registry\ApiTaskRegistry;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class ApiTaskRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiTaskRegistry::reset();
    }

    public function testRegistersAnEndpointUnderItsDefaultPath(): void
    {
        ApiTaskRegistry::registerEndpoint('demo:hello', new AsApi(async: true), 'Say hello', '/srv/app');

        $endpoint = ApiTaskRegistry::all()['demo:hello'];
        self::assertSame('/tasks/demo%3Ahello', $endpoint->path);
        self::assertSame(['POST'], $endpoint->methods);
        self::assertSame('Say hello', $endpoint->description);
        self::assertSame('/srv/app', $endpoint->workingDirectory);
        self::assertTrue($endpoint->async);
        self::assertSame([], $endpoint->locks);
    }

    public function testKeepsACustomPath(): void
    {
        ApiTaskRegistry::registerEndpoint('demo:hello', new AsApi(path: '/hello', methods: ['GET']), '', null);

        $endpoint = ApiTaskRegistry::all()['demo:hello'];
        self::assertSame('/hello', $endpoint->path);
        self::assertSame(['GET'], $endpoint->methods);
    }

    public function testNormalizesLocks(): void
    {
        ApiTaskRegistry::registerEndpoint('demo:single', new AsApi(lock: 'shop:{project}'), '', null);
        ApiTaskRegistry::registerEndpoint('demo:list', new AsApi(lock: ['', 'shop:{project}', '  ', 'theme']), '', null);

        self::assertSame(['shop:{project}'], ApiTaskRegistry::all()['demo:single']->locks);
        self::assertSame(['shop:{project}', 'theme'], ApiTaskRegistry::all()['demo:list']->locks);
    }

    public function testResetForgetsTheEndpoints(): void
    {
        ApiTaskRegistry::registerEndpoint('demo:hello', new AsApi(), '', null);

        ApiTaskRegistry::reset();

        self::assertSame([], ApiTaskRegistry::all());
    }
}
