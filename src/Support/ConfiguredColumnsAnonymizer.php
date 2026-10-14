<?php

declare(strict_types=1);

namespace Filagora\Compliance\Support;

use Filagora\Compliance\Contracts\UserAnonymizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash as PasswordHash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The default anonymiser: sets each configured column to its replacement (`{id}` is the user's id),
 * skipping columns the table does not have, then makes the password unusable and the remember token
 * new, so no session or reset link outlives the erasure.
 */
final class ConfiguredColumnsAnonymizer implements UserAnonymizer
{
    public function anonymize(Model $user): void
    {
        $columns = Schema::getColumnListing($user->getTable());
        $values = [];

        foreach ((array) config('filagora-compliance.anonymize.attributes', []) as $column => $replacement) {
            if (is_string($column) && in_array($column, $columns, true)) {
                $values[$column] = is_string($replacement) ? str_replace('{id}', (string) $user->getKey(), $replacement) : $replacement;
            }
        }

        if (in_array('password', $columns, true)) {
            $values['password'] = PasswordHash::make(Str::random(80));
        }

        if (in_array('remember_token', $columns, true)) {
            $values['remember_token'] = Str::random(60);
        }

        $user->forceFill($values)->save();
    }
}
