<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

final class AuthToken
{
    public static function resolve(): ?string
    {
        $raw = getenv('CASTOR_API_TOKEN');

        return \is_string($raw) && '' !== $raw ? $raw : null;
    }
}
