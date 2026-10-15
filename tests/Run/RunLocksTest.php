<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Run;

use Castor\Api\Run\RunLocks;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RunLocksTest extends TestCase
{
    public function testPlaceholdersAreReplacedByPayloadValues(): void
    {
        self::assertSame(
            ['shop:acme', 'theme'],
            RunLocks::resolve(['shop:{project}', 'theme'], ['project' => ' acme ']),
        );
    }

    public function testTemplatesWithoutPayloadValueAreDropped(): void
    {
        self::assertSame(['theme'], RunLocks::resolve(['shop:{project}', 'theme'], []));
        self::assertSame([], RunLocks::resolve(['shop:{project}'], ['project' => '']));
        self::assertSame([], RunLocks::resolve(['shop:{project}'], ['project' => ['a']]));
    }

    public function testDuplicatesAreRemoved(): void
    {
        self::assertSame(['shop:acme'], RunLocks::resolve(['shop:{project}', 'shop:{slug}'], ['project' => 'acme', 'slug' => 'acme']));
    }
}
