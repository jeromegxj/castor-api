<?php

declare(strict_types=1);

namespace Castor\Api\Tests\OpenApi;

use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\OpenApi\OperationKind;
use Castor\Api\Tests\TemporaryProjectTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversNothing]
final class OpenApiLoaderTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    private string $openapiPath;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('loader');
        $this->openapiPath = self::writeOpenApi($this->projectRoot, [
            '/tasks/demo%3Ahello/start' => ['post' => [
                'operationId' => 'demo:hello.start',
                'x-castor-working-directory' => '/srv/app/sub',
                'x-castor-locks' => ['greeting:{name}', '', 42],
                'responses' => ['202' => ['description' => 'Accepted']],
            ]],
        ]);
    }

    protected function tearDown(): void
    {
        putenv('CASTOR_BINARY');
        self::removeDirectory($this->projectRoot);
    }

    public function testResolvesTheProjectRootAndTheCastorBinary(): void
    {
        $loader = new OpenApiLoader($this->openapiPath);

        self::assertSame($this->projectRoot, $loader->getProjectRoot());
        self::assertSame('castor', $loader->getCastorBinary());

        putenv('CASTOR_BINARY=/usr/local/bin/castor');

        self::assertSame('/usr/local/bin/castor', new OpenApiLoader($this->openapiPath)->getCastorBinary());
    }

    public function testMatchesAnOperation(): void
    {
        $context = new OpenApiLoader($this->openapiPath)->match(Request::create('/tasks/demo:hello/start', 'POST'));

        self::assertNotNull($context);
        self::assertSame(OperationKind::Start, $context->kind);
        self::assertSame('demo:hello', $context->taskName);
        self::assertSame('POST', $context->method);
        self::assertSame('/srv/app/sub', $context->workingDirectory);
        self::assertSame(['greeting:{name}'], $context->locks);
        self::assertNull($context->requestSchema);
    }

    public function testDoesNotMatchAnUnknownPathOrMethod(): void
    {
        $loader = new OpenApiLoader($this->openapiPath);

        self::assertNull($loader->match(Request::create('/tasks/demo:other/start', 'POST')));
        self::assertNull($loader->match(Request::create('/tasks/demo:hello/start', 'GET')));
    }

    public function testFailsWhenTheSpecIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read OpenAPI spec');

        new OpenApiLoader($this->projectRoot . '/missing.json');
    }
}
