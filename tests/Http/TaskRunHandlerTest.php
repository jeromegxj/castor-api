<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Http;

use Castor\Api\Http\TaskRunHandler;
use Castor\Api\OpenApi\OpenApiLoader;
use Castor\Api\OpenApi\OperationContext;
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
final class TaskRunHandlerTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    private OpenApiLoader $loader;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('run-handler');
        putenv('CASTOR_BINARY=' . self::createFakeCastorBinary($this->projectRoot));

        $operation = [
            'requestBody' => ['content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
                'x-castor-arguments' => [],
                'x-castor-options' => ['name'],
            ]]]],
            'responses' => ['200' => ['description' => 'OK']],
        ];

        $this->loader = new OpenApiLoader(self::writeOpenApi($this->projectRoot, [
            '/tasks/demo%3Ahello/run' => ['post' => ['operationId' => 'demo:hello', 'x-castor-locks' => ['greeting:{name}']] + $operation],
            '/tasks/demo%3Afail/run' => ['post' => ['operationId' => 'demo:fail'] + $operation],
        ]));
    }

    protected function tearDown(): void
    {
        putenv('CASTOR_BINARY');
        self::removeDirectory($this->projectRoot);
    }

    public function testRunsTheTaskWithThePayloadAsCliArguments(): void
    {
        $request = self::request('/tasks/demo:hello/run', '{"name": "Castor"}');

        $response = TaskRunHandler::run($this->loader, $this->match($request), $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = self::decode($response);
        self::assertSame('demo:hello', $data['task']);
        self::assertSame(0, $data['exitCode']);
        self::assertSame('demo:hello --name=Castor', $data['stdout']);
        self::assertSame('', $data['stderr']);
    }

    public function testReportsATaskFailure(): void
    {
        $request = self::request('/tasks/demo:fail/run', '{}');

        $response = TaskRunHandler::run($this->loader, $this->match($request), $request);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $data = self::decode($response);
        self::assertSame(3, $data['exitCode']);
        self::assertSame('boom', $data['stderr']);
    }

    public function testRejectsAnotherMethodThanTheOperationOne(): void
    {
        $operation = $this->match(self::request('/tasks/demo:hello/run', '{}'));

        $response = TaskRunHandler::run($this->loader, $operation, Request::create('/tasks/demo:hello/run', 'GET'));

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }

    public function testRejectsAnInvalidPayload(): void
    {
        $request = self::request('/tasks/demo:hello/run', 'not json');

        $response = TaskRunHandler::run($this->loader, $this->match($request), $request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testIsRefusedWhileAnAsyncRunHoldsTheSameLock(): void
    {
        $run = new RunStore($this->projectRoot)->create('demo:hello', [], 'castor', null, ['greeting:Castor'], ['name' => 'Castor']);
        $request = self::request('/tasks/demo:hello/run', '{"name": "Castor"}');

        $response = TaskRunHandler::run($this->loader, $this->match($request), $request);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame($run->id, self::decode($response)['conflict']['id']);
    }

    private function match(Request $request): OperationContext
    {
        $operation = $this->loader->match($request);
        self::assertNotNull($operation);

        return $operation;
    }

    private static function request(string $path, string $content): Request
    {
        $request = Request::create($path, 'POST', content: $content);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(Response $response): array
    {
        /* @var array<string, mixed> */
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
