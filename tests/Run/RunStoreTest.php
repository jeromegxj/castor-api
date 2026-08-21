<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Run;

use Jolicode\CastorApi\Run\RunRecord;
use Jolicode\CastorApi\Run\RunStatus;
use Jolicode\CastorApi\Run\RunStore;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RunStoreTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/castor-api-runs-' . uniqid('', true);
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

    public function testCreateGetAndUpdate(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create(
            task: 'demo:slow',
            cliArgs: ['--seconds=2'],
            castorBinary: 'castor',
            workingDirectory: null,
        );

        self::assertSame(RunStatus::Pending, $record->status);

        $loaded = $store->get($record->id);
        self::assertInstanceOf(RunRecord::class, $loaded);
        self::assertSame('demo:slow', $loaded->task);
        self::assertSame(['--seconds=2'], $loaded->cliArgs);

        $loaded->status = RunStatus::Completed;
        $loaded->exitCode = 0;
        $loaded->stdout = 'done';
        $store->save($loaded);

        $updated = $store->get($record->id);
        self::assertNotNull($updated);
        self::assertSame(RunStatus::Completed, $updated->status);
        self::assertSame('done', $updated->stdout);
    }

    public function testPurgeExpiredDeletesRunsOlderThan24Hours(): void
    {
        $runsDir = $this->projectRoot . '/.castor/api/runs';
        $now = 1_700_000_000;
        $oldRunId = 'old-run-id';
        $recentRunId = 'recent-run-id';

        file_put_contents($runsDir . '/' . $oldRunId . '.json', json_encode([
            'id' => $oldRunId,
            'task' => 'demo:slow',
            'status' => 'completed',
            'cliArgs' => [],
            'projectRoot' => $this->projectRoot,
            'castorBinary' => 'castor',
            'workingDirectory' => null,
            'createdAt' => $now - RunStore::RETENTION_SECONDS - 1,
        ], JSON_THROW_ON_ERROR));

        file_put_contents($runsDir . '/' . $recentRunId . '.json', json_encode([
            'id' => $recentRunId,
            'task' => 'demo:slow',
            'status' => 'completed',
            'cliArgs' => [],
            'projectRoot' => $this->projectRoot,
            'castorBinary' => 'castor',
            'workingDirectory' => null,
            'createdAt' => $now - RunStore::RETENTION_SECONDS + 60,
        ], JSON_THROW_ON_ERROR));

        $store = new RunStore($this->projectRoot);

        self::assertSame(1, $store->purgeExpired($now));
        self::assertFileDoesNotExist($runsDir . '/' . $oldRunId . '.json');
        self::assertFileExists($runsDir . '/' . $recentRunId . '.json');
    }

    public function testCreatePurgesExpiredRuns(): void
    {
        $runsDir = $this->projectRoot . '/.castor/api/runs';
        $oldRunId = 'old-run-id';

        file_put_contents($runsDir . '/' . $oldRunId . '.json', json_encode([
            'id' => $oldRunId,
            'task' => 'demo:slow',
            'status' => 'failed',
            'cliArgs' => [],
            'projectRoot' => $this->projectRoot,
            'castorBinary' => 'castor',
            'workingDirectory' => null,
            'createdAt' => time() - RunStore::RETENTION_SECONDS - 3600,
        ], JSON_THROW_ON_ERROR));

        $store = new RunStore($this->projectRoot);
        $store->create('demo:slow', [], 'castor', null);

        self::assertFileDoesNotExist($runsDir . '/' . $oldRunId . '.json');
        self::assertCount(1, glob($runsDir . '/*.json') ?: []);
    }
}
