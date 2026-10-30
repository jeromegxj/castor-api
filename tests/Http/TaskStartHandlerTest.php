<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Http;

use Castor\Api\Http\TaskStartHandler;
use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\Run\RunRecord;
use Castor\Api\Run\RunStatus;
use Castor\Api\Run\RunStore;
use Castor\Api\Tests\TemporaryProjectTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversNothing]
final class TaskStartHandlerTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    private OpenApiLoader $loader;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('start-handler');
        putenv('CASTOR_BINARY=' . self::createFakeCastorBinary($this->projectRoot));

        $this->loader = new OpenApiLoader(self::writeOpenApi($this->projectRoot, [
            '/tasks/demo%3Ahello/start' => ['post' => [
                'operationId' => 'demo:hello.start',
                'requestBody' => ['content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string']],
                    'x-castor-arguments' => [],
                    'x-castor-options' => ['name'],
                ]]]],
                'responses' => ['202' => ['description' => 'Accepted']],
            ]],
        ]));
    }

    protected function tearDown(): void
    {
        putenv('CASTOR_BINARY');
        self::removeDirectory($this->projectRoot);
    }

    public function testStartsTheTaskInABackgroundWorker(): void
    {
        $request = Request::create('/tasks/demo:hello/start', 'POST', content: '{"name": "Castor"}');
        $operation = $this->loader->match($request);
        self::assertNotNull($operation);

        $response = TaskStartHandler::start($this->loader, $operation, $request, \dirname(__DIR__, 2));

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        /** @var array{id: string, task: string, status: string} $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('demo:hello', $data['task']);
        self::assertSame(RunStatus::Pending->value, $data['status']);

        $run = $this->waitForRun($data['id']);
        self::assertSame(RunStatus::Completed, $run->status);
        self::assertSame('demo:hello --name=Castor', $run->stdout);
    }

    public function testRejectsAnotherMethodThanPost(): void
    {
        $operation = $this->loader->match(Request::create('/tasks/demo:hello/start', 'POST'));
        self::assertNotNull($operation);

        $response = TaskStartHandler::start($this->loader, $operation, Request::create('/tasks/demo:hello/start', 'GET'), \dirname(__DIR__, 2));

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }

    private function waitForRun(string $id): RunRecord
    {
        $store = new RunStore($this->projectRoot);
        $deadline = microtime(true) + 10;

        do {
            $run = $store->get($id);
            self::assertNotNull($run);

            if (!$run->isActive()) {
                return $run;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail(\sprintf('Run "%s" did not finish within 10 seconds.', $id));
    }
}
