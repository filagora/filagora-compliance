<?php

declare(strict_types=1);

namespace Filagora\Compliance\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Replaces who a user is, in the host's own user table, once every package has erased what it
 * holds about them. What counts as identity (name, email, username, avatar, two-factor secrets)
 * is the host's to say; bind your own to cover your columns. The default blanks the columns in
 * `filagora-compliance.anonymize.attributes` and makes the password unusable.
 */
interface UserAnonymizer
{
    public function anonymize(Model $user): void;
}
