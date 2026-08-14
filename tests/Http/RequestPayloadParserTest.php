<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Tests\Http;

use Jolicode\CastorApi\Http\RequestPayloadParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @coversNothing
 */
final class RequestPayloadParserTest extends TestCase
{
    public function testParsesEmptyBodyAsEmptyArray(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '');

        self::assertSame([], RequestPayloadParser::parse($request));
    }

    public function testParsesJsonObject(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '{"name": "Castor"}');

        self::assertSame(['name' => 'Castor'], RequestPayloadParser::parse($request));
    }

    public function testRejectsInvalidJson(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '{invalid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON payload');

        RequestPayloadParser::parse($request);
    }

    public function testRejectsNonObjectJson(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '["not", "an", "object"]');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON payload must be an object');

        RequestPayloadParser::parse($request);
    }
}
