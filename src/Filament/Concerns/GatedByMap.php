<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Concerns;

use Filagora\Core\Filament\Concerns\GatedByAbility;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Gates a resource by a map of action => ability, with a default for any action the map does not
 * name (null denies it). A resource using this declares `abilities()`.
 */
trait GatedByMap
{
    use GatedByAbility;

    /**
     * @return array{default: string|null, read?: string|null, create?: string|null, delete?: string|null}
     */
    abstract protected static function abilities(): array;

    protected static function abilityFor(string|UnitEnum $action, ?Model $record): ?string
    {
        $abilities = static::abilities();

        return match (true) {
            in_array($action, ['viewAny', 'view'], true) => $abilities['read'] ?? $abilities['default'],
            $action === 'create' => array_key_exists('create', $abilities) ? $abilities['create'] : $abilities['default'],
            in_array($action, ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny'], true) => array_key_exists('delete', $abilities) ? $abilities['delete'] : $abilities['default'],
            default => $abilities['default'],
        };
    }
}
