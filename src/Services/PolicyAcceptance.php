<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Compliance\Support\Hash;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a signed-in user has accepted the active privacy policy. Enforcement can be switched
 * off with the setting `enforce_gdpr` (on unless set otherwise). The active version's id is cached
 * for a minute and forgotten when a version changes.
 */
class PolicyAcceptance
{
    public const SETTING = 'enforce_gdpr';

    private const CACHE_SECONDS = 60;

    public function __construct(private readonly SettingsRegistry $settings) {}

    public static function activeCacheKey(): string
    {
        return 'compliance:privacy_policy:active_id';
    }

    public function isEnforced(): bool
    {
        return $this->settings->boolean(self::SETTING, true);
    }

    /**
     * The version users accept: the active one, latest published first.
     */
    public function activePolicyVersion(): ?PrivacyPolicyVersion
    {
        $id = Cache::remember(self::activeCacheKey(), self::CACHE_SECONDS, fn (): ?int => $this->queryActive()?->id);

        if ($id === null) {
            return null;
        }

        /** @var PrivacyPolicyVersion|null */
        return Models::class('privacy_policy_version')::query()->find($id);
    }

    public function latestAcceptance(Authenticatable $user): ?UserPrivacyPolicyAcceptance
    {
        /** @var UserPrivacyPolicyAcceptance|null */
        return Models::class('user_privacy_policy_acceptance')::query()
            ->with('policyVersion')
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * True when there is nothing to accept (enforcement off, or no active version) or the user
     * has accepted the active version. One indexed lookup, the active version itself cached.
     */
    public function hasAcknowledgedActivePolicy(Authenticatable $user): bool
    {
        if (! $this->isEnforced()) {
            return true;
        }

        $active = Cache::remember(self::activeCacheKey(), self::CACHE_SECONDS, fn (): ?int => $this->queryActive()?->id);

        if ($active === null) {
            return true;
        }

        return Models::class('user_privacy_policy_acceptance')::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('privacy_policy_version_id', $active)
            ->exists();
    }

    /**
     * @return array{enforcementEnabled: bool, activePolicy: PrivacyPolicyVersion|null, latestAcceptance: UserPrivacyPolicyAcceptance|null, hasAcknowledgedActivePolicy: bool}
     */
    public function stateFor(Authenticatable $user): array
    {
        $enforced = $this->isEnforced();
        $active = $enforced ? $this->activePolicyVersion() : null;
        $latest = $active !== null ? $this->latestAcceptance($user) : null;

        return [
            'enforcementEnabled' => $enforced,
            'activePolicy' => $active,
            'latestAcceptance' => $latest,
            'hasAcknowledgedActivePolicy' => $active === null || ($latest !== null && (int) $latest->privacy_policy_version_id === (int) $active->id),
        ];
    }

    /**
     * Record that the user accepted the active version (again, if they had). Null when there is none.
     */
    public function accept(Authenticatable $user, ?string $ip = null, ?string $userAgent = null): ?UserPrivacyPolicyAcceptance
    {
        $active = $this->activePolicyVersion();

        if ($active === null) {
            return null;
        }

        /** @var UserPrivacyPolicyAcceptance $acceptance */
        $acceptance = Models::class('user_privacy_policy_acceptance')::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier(), 'privacy_policy_version_id' => $active->id],
            ['accepted_at' => now(), 'ip_hash' => Hash::nullable($ip), 'user_agent_hash' => Hash::nullable($userAgent)],
        );

        return $acceptance->load('policyVersion');
    }

    private function queryActive(): ?PrivacyPolicyVersion
    {
        /** @var PrivacyPolicyVersion|null */
        return Models::class('privacy_policy_version')::query()
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }
}
