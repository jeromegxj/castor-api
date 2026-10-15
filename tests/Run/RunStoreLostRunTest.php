<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Run;

use Castor\Api\Run\RunStatus;
use Castor\Api\Run\RunStore;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RunStoreLostRunTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/castor-api-lost-' . uniqid('', true);
        mkdir($this->projectRoot . '/.castor/api/runs', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectRoot . '/.castor/api/runs/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->projectRoot . '/.castor/api/runs');
        rmdir($this->projectRoot . '/.castor/api');
        rmdir($this->projectRoot . '/.castor');
        rmdir($this->projectRoot);
    }

    public function testRunningRunWithDeadWorkerIsMarkedFailedWithItsLog(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);
        $record->status = RunStatus::Running;
        $record->pid = 4242;
        $store->save($record);
        file_put_contents(RunStore::logPathFor($this->projectRoot, $record->id), 'PHP Fatal error: boom');

        $result = $store->failIfLost($record, isProcessAlive: static fn (int $pid): bool => false);

        self::assertSame(RunStatus::Failed, $result->status);
        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString('exited before reporting a result', (string) $result->stderr);
        self::assertStringContainsString('PHP Fatal error: boom', (string) $result->stderr);
        self::assertSame(RunStatus::Failed, $store->get($record->id)?->status);
    }

    public function testRunningRunWithLiveWorkerIsUntouched(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);
        $record->status = RunStatus::Running;
        $record->pid = 4242;
        $store->save($record);

        $result = $store->failIfLost($record, isProcessAlive: static fn (int $pid): bool => true);

        self::assertSame(RunStatus::Running, $result->status);
    }

    public function testPendingRunNeverPickedUpIsMarkedFailed(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);

        self::assertSame(RunStatus::Pending, $store->failIfLost($record, $record->createdAt + 10)->status);

        $result = $store->failIfLost($record, $record->createdAt + RunStore::PENDING_TIMEOUT_SECONDS + 1);

        self::assertSame(RunStatus::Failed, $result->status);
        self::assertStringContainsString('never started', (string) $result->stderr);
    }

    public function testResultSavedByTheWorkerMeanwhileWins(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);
        $record->status = RunStatus::Running;
        $record->pid = 4242;
        $store->save($record);

        $finished = $store->get($record->id);
        self::assertNotNull($finished);
        $finished->status = RunStatus::Completed;
        $finished->exitCode = 0;
        $store->save($finished);

        $result = $store->failIfLost($record, isProcessAlive: static fn (int $pid): bool => false);

        self::assertSame(RunStatus::Completed, $result->status);
    }

    public function testPurgeAlsoDeletesTheWorkerLog(): void
    {
        $store = new RunStore($this->projectRoot);
        $record = $store->create('demo:slow', [], 'castor', null);
        $log = RunStore::logPathFor($this->projectRoot, $record->id);
        file_put_contents($log, 'output');

        $store->purgeExpired($record->createdAt + RunStore::RETENTION_SECONDS + 1);

        self::assertFileDoesNotExist($log);
    }

    public function testCurrentProcessIsAlive(): void
    {
        self::assertTrue(RunStore::isProcessAlive((int) getmypid()));
        self::assertFalse(RunStore::isProcessAlive(0));
    }
}
