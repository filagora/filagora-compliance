<?php

declare(strict_types=1);

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\PrivacyOperationAudit;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Models\UserCookieConsent;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;
use Filagora\Compliance\Support\Models;
use Filagora\Compliance\Tests\Support\TestUser;
use Illuminate\Database\QueryException;

it('lists its models in the configuration by default', function () {
    expect(array_keys(config('filagora-compliance.models')))->toBe([
        'cookie_consent_group', 'cookie_consent_cookie', 'cookie_policy_version', 'user_cookie_consent',
        'privacy_policy_version', 'user_privacy_policy_acceptance', 'privacy_request', 'privacy_operation_audit',
    ])->and(Models::class('privacy_request'))->toBe(PrivacyRequest::class);

    config(['filagora.models.privacy_request' => 'Custom\\Request']);
    expect(Models::class('privacy_request'))->toBe('Custom\\Request');
    expect(fn () => Models::class('nope'))->toThrow(InvalidArgumentException::class);
});

it('keeps every stored request type and status, and offers all but the legacy one', function () {
    expect(array_column(PrivacyRequestType::cases(), 'value'))->toBe(['access', 'export', 'rectification', 'erasure', 'restriction', 'objection'])
        ->and(array_keys(PrivacyRequestType::selectableOptions()))->toBe(['export', 'rectification', 'erasure', 'restriction', 'objection'])
        ->and(array_column(PrivacyRequestStatus::cases(), 'value'))->toBe(['received', 'in_review', 'fulfilled', 'rejected'])
        ->and(PrivacyRequestStatus::RECEIVED->isOpen())->toBeTrue()
        ->and(PrivacyRequestStatus::IN_REVIEW->isOpen())->toBeTrue()
        ->and(PrivacyRequestStatus::FULFILLED->isOpen())->toBeFalse()
        ->and(PrivacyRequestStatus::REJECTED->isOpen())->toBeFalse();

    $user = TestUser::make('Requester');

    foreach (PrivacyRequestType::cases() as $type) {
        $request = PrivacyRequest::create(['user_id' => $user->id, 'type' => $type->value])->refresh();

        expect($request->type)->toBe($type);
    }
});

it('starts a request received, not on hold, and stamped with the time it was made', function () {
    $this->travelTo(now()->startOfSecond());
    $request = PrivacyRequest::create(['user_id' => TestUser::make('Requester')->id, 'type' => 'export'])->refresh();

    expect($request->status)->toBe(PrivacyRequestStatus::RECEIVED)
        ->and($request->is_on_hold)->toBeFalse()
        ->and($request->requested_at->equalTo(now()))->toBeTrue()
        ->and($request->fulfilled_at)->toBeNull();

    $earlier = PrivacyRequest::create(['user_id' => $request->user_id, 'type' => 'erasure', 'requested_at' => now()->subDay()])->refresh();
    expect($earlier->requested_at->equalTo(now()->subDay()))->toBeTrue();
});

it('tidies a cookie group: key slugged, text trimmed, a required group on by default', function () {
    $group = CookieConsentGroup::create(['group_key' => ' Site Analytics! ', 'label' => '  Analytics ', 'description' => ' Counts visits ', 'is_required' => true])->refresh();

    expect($group->group_key)->toBe('site_analytics')
        ->and($group->label)->toBe('Analytics')
        ->and($group->description)->toBe('Counts visits')
        ->and($group->default_enabled)->toBeTrue()
        ->and($group->is_enabled)->toBeTrue()
        ->and((string) $group)->toBe('Analytics');

    $optional = CookieConsentGroup::create(['group_key' => 'ads', 'label' => 'Ads'])->refresh();
    expect($optional->default_enabled)->toBeFalse()->and($optional->is_required)->toBeFalse();
});

it('orders a group\'s cookies and trims their text', function () {
    $group = CookieConsentGroup::create(['group_key' => 'g', 'label' => 'G']);
    $second = CookieConsentCookie::create(['group_id' => $group->id, 'cookie_name' => ' b ', 'label' => ' B ', 'provider' => ' Us ', 'sort_order' => 2]);
    $first = CookieConsentCookie::create(['group_id' => $group->id, 'cookie_name' => 'a', 'label' => 'A', 'sort_order' => 1]);

    expect($group->cookies->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($second->refresh()->cookie_name)->toBe('b')
        ->and($second->provider)->toBe('Us')
        ->and($second->group->is($group))->toBeTrue();

    $group->delete();
    expect(CookieConsentCookie::count())->toBe(0);
});

it('keeps only one cookie policy version active, and dates an active one', function () {
    $first = CookiePolicyVersion::create(['version' => '2026.01.01.1', 'label' => 'One', 'is_active' => true])->refresh();
    expect($first->is_active)->toBeTrue()->and($first->published_at)->not->toBeNull();

    $second = CookiePolicyVersion::create(['version' => '2026.02.01.1', 'label' => 'Two', 'is_active' => true])->refresh();
    $draft = CookiePolicyVersion::create(['version' => '2026.03.01.1', 'label' => 'Draft', 'is_active' => false])->refresh();

    expect($first->refresh()->is_active)->toBeFalse()
        ->and($second->refresh()->is_active)->toBeTrue()
        ->and($draft->published_at)->toBeNull()
        ->and(CookiePolicyVersion::query()->where('is_active', true)->count())->toBe(1);
});

it('lets several privacy policy versions stay active, unlike the cookie policy', function () {
    PrivacyPolicyVersion::create(['version' => '1', 'label' => 'One', 'is_active' => true]);
    PrivacyPolicyVersion::create(['version' => '2', 'label' => 'Two', 'is_active' => true]);

    expect(PrivacyPolicyVersion::query()->where('is_active', true)->count())->toBe(2);
});

it('stores a cookie consent as JSON, one per user, and releases it with the version or the user', function () {
    $user = TestUser::make('Visitor');
    $version = CookiePolicyVersion::create(['version' => 'v1', 'label' => 'V1']);
    $consent = UserCookieConsent::create([
        'user_id' => $user->id, 'cookie_policy_version_id' => $version->id,
        'consent' => ['necessary' => true, 'groups' => ['ads' => false], 'cookies' => ['ad_id' => false]],
        'ip_hash' => hash('sha256', 'visitor-address'), 'consented_at' => now(),
    ])->refresh();

    expect($consent->consent['groups'])->toBe(['ads' => false])
        ->and($consent->policyVersion->is($version))->toBeTrue()
        ->and($consent->user->is($user))->toBeTrue()
        ->and($version->userConsents)->toHaveCount(1);

    expect(fn () => UserCookieConsent::create(['user_id' => $user->id, 'consent' => [], 'consented_at' => now()]))->toThrow(QueryException::class);

    $version->delete();
    expect($consent->refresh()->cookie_policy_version_id)->toBeNull();

    $user->delete();
    expect(UserCookieConsent::count())->toBe(0);
});

it('records one acceptance per user and version', function () {
    $user = TestUser::make('Member');
    $version = PrivacyPolicyVersion::create(['version' => '1', 'label' => 'One']);
    UserPrivacyPolicyAcceptance::create(['user_id' => $user->id, 'privacy_policy_version_id' => $version->id, 'accepted_at' => now()]);

    expect($version->acceptances)->toHaveCount(1)
        ->and(fn () => UserPrivacyPolicyAcceptance::create(['user_id' => $user->id, 'privacy_policy_version_id' => $version->id, 'accepted_at' => now()]))->toThrow(QueryException::class);

    $version->delete();
    expect(UserPrivacyPolicyAcceptance::count())->toBe(0);
});

it('keeps a request\'s audits and replies in order, and loses them with the request', function () {
    $user = TestUser::make('Requester');
    $staff = TestUser::make('Staff');
    $request = PrivacyRequest::create(['user_id' => $user->id, 'type' => 'export', 'reviewer_id' => $staff->id])->refresh();
    PrivacyOperationAudit::create(['privacy_request_id' => $request->id, 'request_user_id' => $user->id, 'actor_user_id' => $staff->id, 'action' => 'reply_sent', 'meta' => ['message' => 'Hello']]);
    PrivacyOperationAudit::create(['privacy_request_id' => $request->id, 'request_user_id' => $user->id, 'action' => 'export_fulfilled']);

    expect($request->operationAudits)->toHaveCount(2)
        ->and($request->replies->pluck('meta.message')->all())->toBe(['Hello'])
        ->and($request->reviewer->is($staff))->toBeTrue()
        ->and($request->operationAudits->first()->actorUser->is($staff))->toBeTrue();

    $staff->delete();
    expect($request->refresh()->reviewer_id)->toBeNull()
        ->and(PrivacyOperationAudit::query()->whereNull('actor_user_id')->count())->toBe(2);

    $request->delete();
    expect(PrivacyOperationAudit::count())->toBe(0);
});
