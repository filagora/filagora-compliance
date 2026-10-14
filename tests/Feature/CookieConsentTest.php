<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\UserCookieConsent;
use Filagora\Compliance\Services\CookieConsent;
use Filagora\Compliance\Tests\Support\TestUser;
use Filagora\Core\Settings\SettingsRegistry;

function consentGroup(string $key, array $attributes = [], array $cookies = []): CookieConsentGroup
{
    $group = CookieConsentGroup::create(array_merge(['group_key' => $key, 'label' => ucfirst($key)], $attributes));

    foreach ($cookies as $order => $cookie) {
        CookieConsentCookie::create(array_merge(['group_id' => $group->id, 'label' => $cookie['cookie_name'], 'sort_order' => $order], $cookie));
    }

    return $group;
}

function consentService(): CookieConsent
{
    return app(CookieConsent::class);
}

it('is on unless the setting turns it off', function () {
    expect(consentService()->isWorkflowEnabled())->toBeTrue();

    app(SettingsRegistry::class)->set('enable_cookie_consent_workflow', false);
    consentGroup('analytics');

    expect(consentService()->isWorkflowEnabled())->toBeFalse()
        ->and(consentService()->enabledGroups())->toBe([]);
});

it('lists enabled groups and cookies in order, leaving out what is switched off', function () {
    consentGroup('ads', ['sort_order' => 2], [['cookie_name' => 'ad_id']]);
    consentGroup('necessary', ['sort_order' => 1, 'is_required' => true, 'description' => ' Needed '], [
        ['cookie_name' => 'session', 'provider' => 'Us', 'retention_period' => '1 day'],
        ['cookie_name' => 'old', 'is_enabled' => false],
    ]);
    consentGroup('retired', ['sort_order' => 3, 'is_enabled' => false], [['cookie_name' => 'gone']]);

    $groups = consentService()->enabledGroups();

    expect(array_column($groups, 'key'))->toBe(['necessary', 'ads'])
        ->and($groups[0])->toMatchArray(['label' => 'Necessary', 'description' => 'Needed', 'required' => true, 'defaultEnabled' => true])
        ->and($groups[0]['cookies'])->toBe([['key' => 'session', 'label' => 'session', 'description' => '', 'provider' => 'Us', 'retentionPeriod' => '1 day', 'required' => true, 'defaultEnabled' => true]])
        ->and($groups[1]['cookies'][0]['defaultEnabled'])->toBeFalse();
});

it('forces a required group on, whatever was submitted', function () {
    consentGroup('necessary', ['is_required' => true], [['cookie_name' => 'session']]);

    $consent = consentService()->normalize(['groups' => ['necessary' => false], 'cookies' => ['session' => false]], now());

    expect($consent['necessary'])->toBeTrue()
        ->and($consent['groups'])->toBe(['necessary' => true])
        ->and($consent['cookies'])->toBe(['session' => true]);
});

it('forces a required group on even when it lists no cookies', function () {
    consentGroup('necessary', ['is_required' => true]);

    expect(consentService()->normalize(['groups' => ['necessary' => false]], now())['groups'])->toBe(['necessary' => true]);
});

it('takes the cookie\'s own choice first, then its group\'s, then the default', function () {
    consentGroup('analytics', ['default_enabled' => true], [['cookie_name' => 'a'], ['cookie_name' => 'b'], ['cookie_name' => 'c']]);
    consentGroup('ads', [], [['cookie_name' => 'd'], ['cookie_name' => 'e']]);

    $consent = consentService()->normalize(['groups' => ['analytics' => false], 'cookies' => ['a' => true, 'd' => true]], now());

    expect($consent['cookies'])->toBe(['a' => true, 'b' => false, 'c' => false, 'd' => true, 'e' => false])
        ->and($consent['groups'])->toBe(['analytics' => true, 'ads' => true]);
});

it('turns a group on only when one of its cookies is on', function () {
    consentGroup('ads', [], [['cookie_name' => 'd'], ['cookie_name' => 'e']]);

    expect(consentService()->normalize(['cookies' => ['d' => false, 'e' => false]], now())['groups'])->toBe(['ads' => false])
        ->and(consentService()->normalize(['groups' => ['ads' => true]], now())['groups'])->toBe(['ads' => true])
        ->and(consentService()->normalize(['cookies' => ['e' => true]], now())['groups'])->toBe(['ads' => true]);
});

it('lets a group with no cookies take its submitted value or its default, and ignores what is not declared', function () {
    consentGroup('marketing', ['default_enabled' => true]);
    consentGroup('social');

    $consent = consentService()->normalize(['groups' => ['social' => true, 'surprise' => true], 'cookies' => ['mystery' => true]], now());

    expect($consent['groups'])->toBe(['marketing' => true, 'social' => true])
        ->and($consent['cookies'])->toBe([])
        ->and(consentService()->normalize(null, now())['groups'])->toBe(['marketing' => true, 'social' => false]);
});

it('stamps the normalised choice with the time it was made', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));

    expect(consentService()->normalize([], now())['updatedAt'])->toBe(now()->toIso8601String());
});

it('stores one choice per user under the active policy version, replacing it each time, with only hashes of the address', function () {
    consentGroup('ads', [], [['cookie_name' => 'ad_id']]);
    $user = TestUser::make('Visitor');
    $version = CookiePolicyVersion::create(['version' => 'v1', 'label' => 'V1', 'is_active' => true]);

    $first = consentService()->save($user, ['cookies' => ['ad_id' => true]], 'visitor-address', 'a browser');
    $again = consentService()->save($user, ['cookies' => ['ad_id' => false]], null, null);

    expect(UserCookieConsent::count())->toBe(1)
        ->and($first->cookie_policy_version_id)->toBe($version->id)
        ->and($first->ip_hash)->toBe(hash('sha256', 'visitor-address'))
        ->and($first->user_agent_hash)->toBe(hash('sha256', 'a browser'))
        ->and($again->consent['cookies'])->toBe(['ad_id' => false])
        ->and($again->ip_hash)->toBeNull()
        ->and($again->policyVersion->is($version))->toBeTrue()
        ->and(consentService()->forUser($user)->consent['cookies'])->toBe(['ad_id' => false])
        ->and(consentService()->forUser(TestUser::make('Other')))->toBeNull();
});

it('uses the latest published active version as the active one', function () {
    $older = CookiePolicyVersion::create(['version' => 'v1', 'label' => 'V1', 'is_active' => true, 'published_at' => now()->subDay()]);
    expect(consentService()->activePolicyVersion()->is($older))->toBeTrue();

    $older->update(['is_active' => false]);
    expect(consentService()->activePolicyVersion())->toBeNull();
});

it('asks again only when the stored choice is under a version that is no longer active', function () {
    $user = TestUser::make('Visitor');
    $service = consentService();

    expect($service->requiresReconsent(null))->toBeFalse();

    $record = $service->save($user, [], null, null);
    expect($service->requiresReconsent($record))->toBeFalse();

    $old = CookiePolicyVersion::create(['version' => 'v1', 'label' => 'V1', 'is_active' => true]);
    $record = $service->save($user, [], null, null);
    expect($service->requiresReconsent($record))->toBeFalse();

    CookiePolicyVersion::create(['version' => 'v2', 'label' => 'V2', 'is_active' => true]);
    expect($service->requiresReconsent($record->refresh()))->toBeTrue()
        ->and($old->refresh()->is_active)->toBeFalse();
});
