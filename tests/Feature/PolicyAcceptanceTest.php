<?php

declare(strict_types=1);

use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Compliance\Tests\Support\TestUser;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Cache;

function acceptanceService(): PolicyAcceptance
{
    return app(PolicyAcceptance::class);
}

function privacyVersion(string $version, array $attributes = []): PrivacyPolicyVersion
{
    return PrivacyPolicyVersion::create(array_merge(['version' => $version, 'label' => "Policy {$version}", 'is_active' => true, 'published_at' => now()], $attributes));
}

it('has nothing to accept when enforcement is off or there is no active version', function () {
    $user = TestUser::make('Member');

    expect(acceptanceService()->isEnforced())->toBeTrue()
        ->and(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeTrue()
        ->and(acceptanceService()->stateFor($user))->toMatchArray(['enforcementEnabled' => true, 'activePolicy' => null, 'latestAcceptance' => null, 'hasAcknowledgedActivePolicy' => true]);

    privacyVersion('1');
    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeFalse();

    app(SettingsRegistry::class)->set('enforce_gdpr', false);
    expect(acceptanceService()->isEnforced())->toBeFalse()
        ->and(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeTrue()
        ->and(acceptanceService()->stateFor($user))->toMatchArray(['enforcementEnabled' => false, 'activePolicy' => null, 'hasAcknowledgedActivePolicy' => true]);
});

it('takes the latest published active version as the one to accept', function () {
    privacyVersion('1', ['published_at' => now()->subDays(2)->setTime(12, 0)]);
    $newest = privacyVersion('2', ['published_at' => now()->subDay()]);
    privacyVersion('3', ['is_active' => false, 'published_at' => now()]);

    expect(acceptanceService()->activePolicyVersion()->is($newest))->toBeTrue();
});

it('records an acceptance once per user and version, with only hashes', function () {
    $user = TestUser::make('Member');
    $version = privacyVersion('1');

    $first = acceptanceService()->accept($user, 'member-address', 'a browser');
    $second = acceptanceService()->accept($user, null, null);

    expect(UserPrivacyPolicyAcceptance::count())->toBe(1)
        ->and($first->privacy_policy_version_id)->toBe($version->id)
        ->and($first->ip_hash)->toBe(hash('sha256', 'member-address'))
        ->and($second->ip_hash)->toBeNull()
        ->and($second->policyVersion->is($version))->toBeTrue();
});

it('cannot accept when there is no active version', function () {
    expect(acceptanceService()->accept(TestUser::make('Member')))->toBeNull()
        ->and(UserPrivacyPolicyAcceptance::count())->toBe(0);
});

it('knows at once when a version is published, removed or accepted, despite the cache', function () {
    $user = TestUser::make('Member');
    privacyVersion('1');

    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeFalse();
    acceptanceService()->accept($user);
    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeTrue()
        ->and(Cache::has(PolicyAcceptance::activeCacheKey()))->toBeTrue();

    $second = privacyVersion('2', ['published_at' => now()->addMinute()]);
    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeFalse();

    acceptanceService()->accept($user);
    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeTrue();

    $second->delete();
    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeTrue()
        ->and(acceptanceService()->activePolicyVersion()->version)->toBe('1');

    UserPrivacyPolicyAcceptance::query()->where('user_id', $user->id)->get()->each->delete();
    expect(acceptanceService()->hasAcknowledgedActivePolicy($user))->toBeFalse();
});

it('reports the full state for a user', function () {
    $user = TestUser::make('Member');
    $first = privacyVersion('1', ['published_at' => now()->subDay()]);
    acceptanceService()->accept($user);
    $second = privacyVersion('2');

    $state = acceptanceService()->stateFor($user);

    expect($state['activePolicy']->is($second))->toBeTrue()
        ->and($state['latestAcceptance']->privacy_policy_version_id)->toBe($first->id)
        ->and($state['hasAcknowledgedActivePolicy'])->toBeFalse();
});
