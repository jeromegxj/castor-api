<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
final class AsApi
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public ?string $path = null,
        public array $methods = ['POST'],
        public bool $exposeSchema = true,
        public bool $async = false,
    ) {
    }
}
