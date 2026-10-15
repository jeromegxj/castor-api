<?php

declare(strict_types=1);

namespace Castor\Api\Run;

final class RunLocks
{
    /**
     * Replaces the "{name}" placeholders of the lock templates with the payload values.
     *
     * A template whose placeholder has no scalar, non-empty value is dropped: the run cannot be scoped, so it
     * does not hold that lock.
     *
     * @param list<string>         $templates
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public static function resolve(array $templates, array $payload): array
    {
        $locks = [];

        foreach ($templates as $template) {
            $missing = false;
            $lock = preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', static function (array $matches) use ($payload, &$missing): string {
                $value = $payload[$matches[1]] ?? null;

                if (!\is_scalar($value) || '' === trim((string) $value)) {
                    $missing = true;

                    return '';
                }

                return trim((string) $value);
            }, $template);

            if (!$missing && null !== $lock) {
                $locks[] = $lock;
            }
        }

        return array_values(array_unique($locks));
    }
}
