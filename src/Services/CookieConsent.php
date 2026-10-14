<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Carbon\CarbonInterface;
use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\UserCookieConsent;
use Filagora\Compliance\Support\Hash;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cookie consent for signed-in users: what the site declares, what a user chose, and whether the
 * choice is out of date. The workflow can be switched off with the setting
 * `enable_cookie_consent_workflow` (on unless set otherwise).
 *
 * @phpstan-type CookieEntry array{key: string, label: string, description: string, provider: string, retentionPeriod: string, required: bool, defaultEnabled: bool}
 * @phpstan-type GroupEntry array{key: string, label: string, description: string, required: bool, defaultEnabled: bool, cookies: list<CookieEntry>}
 */
class CookieConsent
{
    public const SETTING = 'enable_cookie_consent_workflow';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function isWorkflowEnabled(): bool
    {
        return $this->settings->boolean(self::SETTING, true);
    }

    /**
     * The enabled groups with their enabled cookies, in display order; empty when the workflow
     * is off. A group or cookie with a blank key is left out.
     *
     * @return list<GroupEntry>
     */
    public function enabledGroups(): array
    {
        if (! $this->isWorkflowEnabled()) {
            return [];
        }

        $groups = [];

        /** @var Collection<int, CookieConsentGroup> $rows */
        $rows = Models::class('cookie_consent_group')::query()
            ->with(['cookies' => fn ($query) => $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $group) {
            $key = trim((string) $group->group_key);

            if ($key === '') {
                continue;
            }

            $required = (bool) $group->is_required;
            $defaultEnabled = $required || (bool) $group->default_enabled;
            $cookies = [];

            foreach ($group->cookies as $cookie) {
                /** @var CookieConsentCookie $cookie */
                $cookieKey = trim((string) $cookie->cookie_name);

                if ($cookieKey === '') {
                    continue;
                }

                $cookies[] = [
                    'key' => $cookieKey,
                    'label' => trim((string) ($cookie->label ?: $cookieKey)),
                    'description' => trim((string) ($cookie->description ?? '')),
                    'provider' => trim((string) ($cookie->provider ?? '')),
                    'retentionPeriod' => trim((string) ($cookie->retention_period ?? '')),
                    'required' => $required,
                    'defaultEnabled' => $defaultEnabled,
                ];
            }

            $groups[] = [
                'key' => $key,
                'label' => trim((string) $group->label),
                'description' => trim((string) ($group->description ?? '')),
                'required' => $required,
                'defaultEnabled' => $defaultEnabled,
                'cookies' => $cookies,
            ];
        }

        return $groups;
    }

    /**
     * The policy version users consent under: the active one, latest published first.
     */
    public function activePolicyVersion(): ?CookiePolicyVersion
    {
        /** @var CookiePolicyVersion|null */
        return Models::class('cookie_policy_version')::query()
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function forUser(Authenticatable $user): ?UserCookieConsent
    {
        /** @var UserCookieConsent|null */
        return Models::class('user_cookie_consent')::query()
            ->with('policyVersion')
            ->where('user_id', $user->getAuthIdentifier())
            ->first();
    }

    /**
     * Whether a stored choice was given under a policy version that is no longer the active one.
     * With no active version, or no stored choice, nobody needs to be asked again.
     */
    public function requiresReconsent(?UserCookieConsent $record): bool
    {
        $active = $this->activePolicyVersion();

        if ($active === null || $record === null) {
            return false;
        }

        return (int) $record->cookie_policy_version_id !== (int) $active->id;
    }

    /**
     * Store a user's choice (one row per user, replaced each time) under the active policy version.
     *
     * @param  array{groups?: array<string, bool>, cookies?: array<string, bool>}  $payload
     */
    public function save(Authenticatable $user, array $payload, ?string $ip = null, ?string $userAgent = null): UserCookieConsent
    {
        /** @var UserCookieConsent $record */
        $record = Models::class('user_cookie_consent')::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'cookie_policy_version_id' => $this->activePolicyVersion()?->id,
                'consent' => $this->normalize($payload, now()),
                'ip_hash' => Hash::nullable($ip),
                'user_agent_hash' => Hash::nullable($userAgent),
                'consented_at' => now(),
            ],
        );

        return $record->load('policyVersion');
    }

    /**
     * Merge a submitted choice over what the site declares. For each cookie: a required group
     * forces it on; otherwise the submitted cookie value wins, then the submitted value for its
     * group, then the default. A group is on when it is required or any of its cookies is on;
     * a group without cookies takes its submitted value or its default.
     *
     * @param  array{groups?: array<string, bool>, cookies?: array<string, bool>}|null  $payload
     * @return array{necessary: true, groups: array<string, bool>, cookies: array<string, bool>, updatedAt: string}
     */
    public function normalize(?array $payload, CarbonInterface $updatedAt): array
    {
        $declared = $this->declared();
        $incomingGroups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];
        $incomingCookies = is_array($payload['cookies'] ?? null) ? $payload['cookies'] : [];

        $cookies = [];

        foreach ($declared['cookies'] as $cookieKey => $cookieDefault) {
            $groupKey = $declared['cookieGroup'][$cookieKey] ?? null;

            if ($groupKey !== null && ($declared['required'][$groupKey] ?? false)) {
                $cookies[$cookieKey] = true;
            } elseif (array_key_exists($cookieKey, $incomingCookies)) {
                $cookies[$cookieKey] = (bool) $incomingCookies[$cookieKey];
            } elseif ($groupKey !== null && array_key_exists($groupKey, $incomingGroups)) {
                $cookies[$cookieKey] = (bool) $incomingGroups[$groupKey];
            } else {
                $cookies[$cookieKey] = $cookieDefault;
            }
        }

        $groups = [];

        foreach ($declared['groups'] as $groupKey => $groupDefault) {
            $cookieKeys = $declared['groupCookies'][$groupKey] ?? [];

            if ($declared['required'][$groupKey] ?? false) {
                $groups[$groupKey] = true;
            } elseif ($cookieKeys !== []) {
                $groups[$groupKey] = in_array(true, array_map(fn (string $key): bool => $cookies[$key] ?? false, $cookieKeys), true);
            } elseif (array_key_exists($groupKey, $incomingGroups)) {
                $groups[$groupKey] = (bool) $incomingGroups[$groupKey];
            } else {
                $groups[$groupKey] = $groupDefault;
            }
        }

        return [
            'necessary' => true,
            'groups' => $groups,
            'cookies' => $cookies,
            'updatedAt' => $updatedAt->toIso8601String(),
        ];
    }

    /**
     * @return array{groups: array<string, bool>, required: array<string, bool>, groupCookies: array<string, list<string>>, cookieGroup: array<string, string>, cookies: array<string, bool>}
     */
    private function declared(): array
    {
        $declared = ['groups' => [], 'required' => [], 'groupCookies' => [], 'cookieGroup' => [], 'cookies' => []];

        foreach ($this->enabledGroups() as $group) {
            $declared['groups'][$group['key']] = $group['required'] || $group['defaultEnabled'];
            $declared['required'][$group['key']] = $group['required'];
            $declared['groupCookies'][$group['key']] = [];

            foreach ($group['cookies'] as $cookie) {
                $declared['cookies'][$cookie['key']] = $cookie['required'] || $cookie['defaultEnabled'];
                $declared['cookieGroup'][$cookie['key']] = $group['key'];
                $declared['groupCookies'][$group['key']][] = $cookie['key'];
            }
        }

        return $declared;
    }
}
