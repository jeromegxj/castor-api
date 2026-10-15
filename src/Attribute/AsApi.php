<?php

declare(strict_types=1);

namespace Castor\Api\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
final class AsApi
{
    /**
     * @param list<string>        $methods
     * @param string|list<string> $lock    Lock keys held while an async run is pending or running.
     *                                     "{name}" is replaced by the "name" value of the payload.
     *                                     A run whose keys overlap with an active run is refused (409).
     */
    public function __construct(
        public ?string $path = null,
        public array $methods = ['POST'],
        public bool $exposeSchema = true,
        public bool $async = false,
        public string|array $lock = [],
    ) {
    }
}
