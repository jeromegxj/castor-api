<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Run;

use Castor\Api\Run\RunExecutor;
use Castor\Api\Run\RunStatus;
use Castor\Api\Run\RunStore;
use Castor\Api\Tests\TemporaryProjectTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class RunExecutorTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('executor');
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->projectRoot);
    }

    public function testMarksRunAsFailedWhenCastorBinaryIsInvalid(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create(
            task: 'demo:slow',
            cliArgs: [],
            castorBinary: '/path/does/not/exist/castor',
            workingDirectory: null,
        );

        $result = new RunExecutor()->execute($record);
        $persisted = $store->get($record->id);

        self::assertNotNull($persisted);
        self::assertSame(RunStatus::Failed, $result->status);
        self::assertSame(RunStatus::Failed, $persisted->status);
        self::assertSame(1, $result->exitCode);
        self::assertStringStartsWith('Unable to start Castor subprocess: ', (string) $result->stderr);
        self::assertNotNull($result->finishedAt);
    }
}
