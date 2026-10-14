<?php

declare(strict_types=1);

namespace Filagora\Compliance\Support;

/**
 * Stores an address or user agent as a one-way hash, never the raw value.
 */
final class Hash
{
    public static function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : hash('sha256', $trimmed);
    }
}
