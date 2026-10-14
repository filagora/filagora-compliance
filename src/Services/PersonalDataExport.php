<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Core\Privacy\PrivacyContributorRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a person's data export: one JSON document with a section for the user's own account and
 * one for each package or host that registered a `PrivacyContributor`.
 */
class PersonalDataExport
{
    public function __construct(private readonly PrivacyContributorRegistry $contributors) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Model $user): array
    {
        $account = [];

        foreach ((array) config('filagora-compliance.exports.user_attributes', []) as $attribute) {
            if (is_string($attribute)) {
                $value = $user->getAttribute($attribute);
                $account[$attribute] = $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : $value;
            }
        }

        $payload = ['generated_at' => now()->toIso8601String(), 'user' => $account];

        foreach ($this->contributors->all() as $key => $contributor) {
            $payload[$key] = $contributor->export($user);
        }

        return $payload;
    }

    /**
     * Build the export and write it to the configured disk. Returns the path on that disk.
     */
    public function store(Model $user): string
    {
        $path = sprintf(
            '%s/user-%s/privacy-export-%s.json',
            trim((string) config('filagora-compliance.exports.directory', 'exports/privacy'), '/'),
            $user->getKey(),
            now()->format('Ymd_His'),
        );

        Storage::disk(self::disk())->put($path, (string) json_encode($this->build($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $path;
    }

    public static function disk(): string
    {
        return (string) config('filagora-compliance.exports.disk', 'local');
    }
}
