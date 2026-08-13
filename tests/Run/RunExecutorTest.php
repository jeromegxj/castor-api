<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Run;

use Jolicode\CastorApi\Run\RunExecutor;
use Jolicode\CastorApi\Run\RunStatus;
use Jolicode\CastorApi\Run\RunStore;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RunExecutorTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/castor-api-executor-' . uniqid('', true);
        mkdir($this->projectRoot . '/.castor/api/runs', 0o777, true);
    }

    protected function tearDown(): void
    {
        $runsDir = $this->projectRoot . '/.castor/api/runs';

        if (is_dir($runsDir)) {
            foreach (glob($runsDir . '/*.json') ?: [] as $file) {
                unlink($file);
            }

            rmdir($runsDir);
            rmdir($this->projectRoot . '/.castor/api');
            rmdir($this->projectRoot . '/.castor');
            rmdir($this->projectRoot);
        }
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
        self::assertNotNull($result->stderr);
        self::assertNotNull($result->finishedAt);
    }
}
