<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Compliance\Contracts\UserAnonymizer;
use Filagora\Core\Privacy\PrivacyContributorRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Erases a person: every registered `PrivacyContributor` erases what its package holds, then the
 * `UserAnonymizer` replaces who the user is. All in one transaction, so a failure leaves them as
 * they were.
 */
class PersonalDataErasure
{
    public function __construct(
        private readonly PrivacyContributorRegistry $contributors,
        private readonly UserAnonymizer $anonymizer,
    ) {}

    public function erase(Model $user): void
    {
        DB::transaction(function () use ($user): void {
            foreach ($this->contributors->all() as $contributor) {
                $contributor->erase($user);
            }

            $this->anonymizer->anonymize($user);
        });
    }
}
