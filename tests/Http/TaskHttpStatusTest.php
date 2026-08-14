<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Http;

use Jolicode\CastorApi\Http\TaskHttpStatus;
use Jolicode\CastorApi\Run\RunStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @coversNothing
 */
final class TaskHttpStatusTest extends TestCase
{
    public function testFromExitCodeReturnsOkForSuccess(): void
    {
        self::assertSame(Response::HTTP_OK, TaskHttpStatus::fromExitCode(0));
    }

    public function testFromExitCodeReturnsUnprocessableEntityForFailure(): void
    {
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, TaskHttpStatus::fromExitCode(1));
    }

    public function testFromRunStatusReturnsUnprocessableEntityForFailed(): void
    {
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, TaskHttpStatus::fromRunStatus(RunStatus::Failed));
    }

    #[DataProvider('provideFromRunStatusReturnsOkForNonFailedCases')]
    public function testFromRunStatusReturnsOkForNonFailed(RunStatus $status): void
    {
        self::assertSame(Response::HTTP_OK, TaskHttpStatus::fromRunStatus($status));
    }

    /**
     * @return iterable<string, array{RunStatus}>
     */
    public static function provideFromRunStatusReturnsOkForNonFailedCases(): iterable
    {
        yield 'pending' => [RunStatus::Pending];
        yield 'running' => [RunStatus::Running];
        yield 'completed' => [RunStatus::Completed];
    }
}
