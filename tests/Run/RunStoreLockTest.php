<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Run;

use Castor\Api\Run\RunConflictException;
use Castor\Api\Run\RunStatus;
use Castor\Api\Run\RunStore;
use Castor\Api\Tests\TemporaryProjectTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class RunStoreLockTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('locks');
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->projectRoot);
    }

    public function testRunSharingALockWithAnActiveRunIsRefused(): void
    {
        $store = new RunStore($this->projectRoot);
        $first = $store->create('shop:generate', [], 'castor', null, ['shop:acme'], ['project' => 'acme']);

        try {
            $store->create('shop:load', [], 'castor', null, ['shop:acme'], ['project' => 'acme']);
            self::fail('The second run should conflict.');
        } catch (RunConflictException $exception) {
            self::assertSame($first->id, $exception->conflict->id);
            self::assertSame('shop:acme', $exception->lock);
            self::assertStringContainsString('shop:generate', $exception->getMessage());
        }

        self::assertCount(1, $store->active());
    }

    public function testRunsWithDifferentLocksOrNoLockCanRunTogether(): void
    {
        $store = new RunStore($this->projectRoot);
        $store->create('shop:generate', [], 'castor', null, ['shop:acme']);
        $store->create('shop:generate', [], 'castor', null, ['shop:other']);
        $store->create('demo:slow', [], 'castor', null);
        $store->create('demo:slow', [], 'castor', null);

        self::assertCount(4, $store->active());
    }

    public function testLockIsReleasedWhenTheRunFinishes(): void
    {
        $store = new RunStore($this->projectRoot);
        $first = $store->create('shop:generate', [], 'castor', null, ['shop:acme']);
        $first->status = RunStatus::Completed;
        $store->save($first);

        $second = $store->create('shop:load', [], 'castor', null, ['shop:acme']);

        self::assertSame([$second->id], array_map(static fn ($run): string => $run->id, $store->active()));
    }

    public function testLockOfARunWhoseWorkerDiedIsReleased(): void
    {
        $store = new RunStore($this->projectRoot);
        $first = $store->create('shop:generate', [], 'castor', null, ['shop:acme']);
        $first->status = RunStatus::Running;
        $first->pid = 999_999_999;
        $store->save($first);

        $store->create('shop:load', [], 'castor', null, ['shop:acme']);

        self::assertSame(RunStatus::Failed, $store->get($first->id)?->status);
    }

    public function testActiveRunsKeepTheirLocksAndPayload(): void
    {
        $store = new RunStore($this->projectRoot);
        $store->create('shop:generate', ['--project=acme'], 'castor', null, ['shop:acme'], ['project' => 'acme']);

        $summary = $store->active()[0]->toSummaryResponse();

        self::assertSame('shop:generate', $summary['task']);
        self::assertSame('pending', $summary['status']);
        self::assertSame(['shop:acme'], $summary['locks']);
        self::assertSame(['project' => 'acme'], $summary['payload']);
    }
}
