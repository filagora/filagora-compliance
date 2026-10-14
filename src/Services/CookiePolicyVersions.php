<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Support\Models;
use Illuminate\Support\Facades\DB;

/**
 * Creates the next cookie policy version, numbered by day (`2026.10.14.1`, `.2` for a second the
 * same day). It becomes the active version, so users are asked for consent again.
 */
class CookiePolicyVersions
{
    public function createNext(string $label, ?string $notes = null): CookiePolicyVersion
    {
        return DB::transaction(function () use ($label, $notes): CookiePolicyVersion {
            /** @var CookiePolicyVersion */
            return Models::class('cookie_policy_version')::query()->create([
                'version' => $this->nextVersionString(),
                'label' => $label,
                'notes' => $notes,
                'is_active' => true,
                'published_at' => now(),
            ]);
        });
    }

    public function nextVersionString(): string
    {
        $base = now()->format('Y.m.d');

        $highest = Models::class('cookie_policy_version')::query()
            ->lockForUpdate()
            ->where('version', 'like', $base.'.%')
            ->pluck('version')
            ->map(static fn (string $version): int => (int) substr($version, strlen($base) + 1))
            ->max();

        return $base.'.'.(((int) $highest) + 1);
    }
}
