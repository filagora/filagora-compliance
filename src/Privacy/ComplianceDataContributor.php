<?php

declare(strict_types=1);

namespace Filagora\Compliance\Privacy;

use Filagora\Compliance\Support\Models;
use Filagora\Core\Contracts\PrivacyContributor;
use Illuminate\Database\Eloquent\Model;

/**
 * What this package holds about a person: their cookie choice, their policy acceptances and their
 * privacy requests. Erasure removes the cookie choice; acceptances and requests stay, as the record
 * that the person was asked and what was done.
 */
final class ComplianceDataContributor implements PrivacyContributor
{
    public function key(): string
    {
        return 'compliance';
    }

    public function export(Model $user): array
    {
        $id = $user->getKey();

        return [
            'cookie_consent' => Models::class('user_cookie_consent')::query()->where('user_id', $id)->get(['consent', 'ip_hash', 'user_agent_hash', 'consented_at'])->toArray(),
            'privacy_policy_acceptances' => Models::class('user_privacy_policy_acceptance')::query()->where('user_id', $id)->get(['privacy_policy_version_id', 'accepted_at', 'ip_hash', 'user_agent_hash'])->toArray(),
            'privacy_requests' => Models::class('privacy_request')::query()->where('user_id', $id)->get(['type', 'status', 'request_details', 'requested_at', 'reviewed_at', 'fulfilled_at', 'rejected_at'])->toArray(),
        ];
    }

    public function erase(Model $user): void
    {
        Models::class('user_cookie_consent')::query()->where('user_id', $user->getKey())->delete();
    }
}
