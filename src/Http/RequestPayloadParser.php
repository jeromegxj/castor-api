<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Http;

use Symfony\Component\HttpFoundation\Request;

final class RequestPayloadParser
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid JSON payload: ' . $exception->getMessage(), previous: $exception);
        }

        if (!\is_array($payload)) {
            throw new \InvalidArgumentException('JSON payload must be an object.');
        }

        return $payload;
    }
}
