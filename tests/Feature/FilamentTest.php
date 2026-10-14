<?php

declare(strict_types=1);

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\CookieConsentGroupResource;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages\CreateCookieConsentGroup;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages\EditCookieConsentGroup;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages\ListCookieConsentGroups;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\RelationManagers\CookiesRelationManager;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\CookiePolicyVersionResource;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages\CreateCookiePolicyVersion;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages\EditCookiePolicyVersion;
use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\Pages\ListPrivacyOperationAudits;
use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\PrivacyOperationAuditResource;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages\CreatePrivacyPolicyVersion;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages\ListPrivacyPolicyVersions;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\PrivacyPolicyVersionResource;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filagora\Compliance\Filament\Widgets\PrivacyKpiOverview;
use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\PrivacyOperationAudit;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\PrivacyAudit;
use Filagora\Compliance\Services\PrivacyKpis;
use Filagora\Compliance\Support\Abilities;
use Filagora\Compliance\Tests\Support\TestUser;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Notification::fake();
    Storage::fake('local');
});

function complianceStaff(string ...$keys): TestUser
{
    $user = TestUser::make('Staff');

    foreach ($keys as $key) {
        UserEntitlementGrant::create(['user_id' => $user->id, 'entitlement_key' => $key, 'grant_type' => 'manual']);
    }

    actingAs($user);

    return $user;
}

function allCompliance(): TestUser
{
    return complianceStaff(Abilities::VIEW_REQUESTS, Abilities::MANAGE_REQUESTS, Abilities::ERASE, Abilities::COOKIE_POLICIES, Abilities::PRIVACY_POLICIES, Abilities::AUDIT);
}

function pendingRequest(string $type = 'export', array $attributes = []): PrivacyRequest
{
    return PrivacyRequest::create(array_merge(['user_id' => TestUser::make('Requester')->id, 'type' => $type, 'request_details' => 'Please'], $attributes))->refresh();
}

// ------------------------------------------------------------------ gating

it('opens each screen only to the ability that guards it', function () {
    $screens = [
        PrivacyRequestResource::class => Abilities::VIEW_REQUESTS,
        PrivacyOperationAuditResource::class => Abilities::AUDIT,
        CookieConsentGroupResource::class => Abilities::COOKIE_POLICIES,
        CookiePolicyVersionResource::class => Abilities::COOKIE_POLICIES,
        PrivacyPolicyVersionResource::class => Abilities::PRIVACY_POLICIES,
    ];

    actingAs(TestUser::make('Nobody'));
    foreach ($screens as $resource => $ability) {
        expect($resource::canViewAny())->toBeFalse($resource);
    }

    foreach ($screens as $resource => $ability) {
        complianceStaff($ability);
        expect($resource::canViewAny())->toBeTrue($resource);

        foreach ($screens as $other => $otherAbility) {
            if ($otherAbility !== $ability) {
                expect($other::canViewAny())->toBeFalse("{$other} with {$ability}");
            }
        }
    }
});

it('never offers to create a privacy request, and keeps deleting for those who may erase', function () {
    complianceStaff(Abilities::VIEW_REQUESTS, Abilities::MANAGE_REQUESTS);
    expect(PrivacyRequestResource::canCreate())->toBeFalse()->and(PrivacyRequestResource::canDeleteAny())->toBeFalse();

    allCompliance();
    expect(PrivacyRequestResource::canCreate())->toBeFalse()->and(PrivacyRequestResource::canDeleteAny())->toBeTrue();
});

it('keeps the audit read-only', function () {
    allCompliance();

    expect(PrivacyOperationAuditResource::canCreate())->toBeFalse()->and(PrivacyOperationAuditResource::canDeleteAny())->toBeFalse();
});

// ------------------------------------------------------------------ requests

it('lists requests, hiding rejected ones until the filter is cleared', function () {
    allCompliance();
    $open = pendingRequest();
    $rejected = pendingRequest('erasure', ['status' => 'rejected']);

    Livewire::test(ListPrivacyRequests::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$rejected])
        ->removeTableFilter('hide_rejected')
        ->assertCanSeeTableRecords([$open, $rejected])
        ->filterTable('type', 'erasure')
        ->assertCanSeeTableRecords([$rejected])
        ->assertCanNotSeeTableRecords([$open]);
});

it('shows a request with its details', function () {
    allCompliance();
    $request = pendingRequest('export', ['request_details' => 'Send me everything']);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()])->assertSee('Send me everything')->assertSee('Data export');
});

it('changes a request\'s status, holds and releases it, notes it and replies', function () {
    $staff = allCompliance();
    $request = pendingRequest();
    $page = Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()]);

    $page->callAction('set_status', ['status' => 'in_review'])->assertNotified();
    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::IN_REVIEW)->and($request->reviewer_id)->toBe($staff->id);

    $page->assertActionHidden('release')->callAction('hold', ['reason' => 'Court order']);
    expect($request->refresh()->is_on_hold)->toBeTrue()->and($request->hold_reason)->toBe('Court order');

    $page->assertActionHidden('hold')->callAction('release');
    expect($request->refresh()->is_on_hold)->toBeFalse();

    $page->callAction('add_note', ['note' => 'Checked identity']);
    $page->callAction('reply', ['message' => 'We are on it']);

    expect($request->refresh()->admin_notes)->toContain('Checked identity')->toContain('Reply sent to requester: We are on it')
        ->and(PrivacyOperationAudit::query()->where('privacy_request_id', $request->id)->pluck('action')->all())->toBe(['hold_placed', 'hold_released', 'reply_sent']);
});

it('generates an export and offers it for download', function () {
    allCompliance();
    $request = pendingRequest();
    $page = Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()]);

    $page->assertActionHidden('download')->callAction('export')->assertNotified();

    $request->refresh();
    expect($request->status)->toBe(PrivacyRequestStatus::FULFILLED)->and(Storage::disk('local')->exists($request->export_path))->toBeTrue();

    $page->assertActionVisible('download')->assertActionHidden('export')->callAction('download')->assertHasNoActionErrors();
});

it('says so when the export file has gone', function () {
    allCompliance();
    $request = pendingRequest('export', ['status' => 'fulfilled', 'export_path' => 'exports/gone.json']);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()])->callAction('download')->assertNotified('The export file is no longer there.');
});

it('shows a refusal instead of failing when a request on hold is fulfilled', function () {
    allCompliance();
    $request = pendingRequest('export', ['is_on_hold' => true]);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()])->callAction('export')->assertNotified('Not done');

    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::RECEIVED);
});

it('erases a person only for those who may, and only on an erasure request', function () {
    complianceStaff(Abilities::VIEW_REQUESTS, Abilities::MANAGE_REQUESTS);
    $erasure = pendingRequest('erasure');
    $export = pendingRequest('export');

    Livewire::test(ViewPrivacyRequest::class, ['record' => $erasure->getKey()])->assertActionHidden('erase');

    allCompliance();
    Livewire::test(ViewPrivacyRequest::class, ['record' => $export->getKey()])->assertActionHidden('erase');
    $user = $erasure->user;
    Livewire::test(ViewPrivacyRequest::class, ['record' => $erasure->getKey()])->assertActionVisible('erase')->callAction('erase')->assertNotified();

    expect($erasure->refresh()->status)->toBe(PrivacyRequestStatus::FULFILLED)->and($user->refresh()->email)->toContain('anonymized');
});

it('rejects a request with a note', function () {
    allCompliance();
    $request = pendingRequest();

    Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()])->callAction('reject', ['note' => 'Not verified']);

    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::REJECTED)->and($request->admin_notes)->toContain('Not verified');
});

it('hides the actions from those who may only look', function () {
    complianceStaff(Abilities::VIEW_REQUESTS);
    $request = pendingRequest();

    $page = Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()])->assertSuccessful();

    foreach (['set_status', 'hold', 'reply', 'add_note', 'export', 'reject', 'audits'] as $action) {
        $page->assertActionHidden($action);
    }
});

it('offers to reject only a request that is still open', function () {
    allCompliance();
    $open = pendingRequest('export', ['status' => 'in_review']);
    $done = pendingRequest('erasure', ['status' => 'fulfilled']);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $open->getKey()])->assertActionVisible('reject');
    Livewire::test(ViewPrivacyRequest::class, ['record' => $done->getKey()])->assertActionHidden('reject');
});

it('links to the audit of one request, which lists only that request', function () {
    allCompliance();
    $one = pendingRequest();
    $two = pendingRequest('erasure');
    app(PrivacyAudit::class)->record($one, 'reply_sent');
    app(PrivacyAudit::class)->record($two, 'hold_placed');

    Livewire::test(ListPrivacyOperationAudits::class)
        ->assertCountTableRecords(2)
        ->filterTable('request', ['privacy_request_id' => $one->id])
        ->assertCountTableRecords(1)
        ->assertSee('reply_sent');
});

// ------------------------------------------------------------------ cookie groups and policy versions

it('manages cookie groups and their cookies', function () {
    allCompliance();

    Livewire::test(CreateCookieConsentGroup::class)
        ->fillForm(['sort_order' => 1, 'group_key' => 'Analytics!', 'label' => 'Analytics', 'is_required' => false])
        ->call('create')
        ->assertHasNoFormErrors();
    $group = CookieConsentGroup::query()->sole();

    expect($group->group_key)->toBe('analytics');

    Livewire::test(CookiesRelationManager::class, ['ownerRecord' => $group, 'pageClass' => EditCookieConsentGroup::class])
        ->callTableAction('create', data: ['sort_order' => 1, 'cookie_name' => '_ga', 'label' => 'Google Analytics', 'is_enabled' => true])
        ->assertHasNoTableActionErrors();

    Livewire::test(ListCookieConsentGroups::class)->assertCanSeeTableRecords([$group]);
    expect(CookieConsentCookie::query()->sole()->cookie_name)->toBe('_ga');
});

it('shows the cookies of a group only to those who manage the cookie policy', function () {
    $group = CookieConsentGroup::create(['group_key' => 'ads', 'label' => 'Ads']);

    complianceStaff(Abilities::AUDIT);
    expect(CookiesRelationManager::canViewForRecord($group, EditCookieConsentGroup::class))->toBeFalse();

    complianceStaff(Abilities::COOKIE_POLICIES);
    expect(CookiesRelationManager::canViewForRecord($group, EditCookieConsentGroup::class))->toBeTrue();
});

it('forces a required cookie group on by default', function () {
    allCompliance();

    Livewire::test(CreateCookieConsentGroup::class)
        ->fillForm(['sort_order' => 0, 'group_key' => 'necessary', 'label' => 'Necessary', 'is_required' => true])
        ->call('create');

    expect(CookieConsentGroup::query()->sole()->default_enabled)->toBeTrue();
});

it('keeps cookie group and cookie keys unique', function () {
    allCompliance();
    CookieConsentGroup::create(['group_key' => 'ads', 'label' => 'Ads']);

    Livewire::test(CreateCookieConsentGroup::class)
        ->fillForm(['sort_order' => 0, 'group_key' => 'ads', 'label' => 'Again'])
        ->call('create')
        ->assertHasFormErrors(['group_key']);
});

it('activating a cookie policy version deactivates the others', function () {
    allCompliance();
    $old = CookiePolicyVersion::create(['version' => '2026.01.01.1', 'label' => 'Old', 'is_active' => true]);

    Livewire::test(CreateCookiePolicyVersion::class)
        ->fillForm(['version' => '2026.02.01.1', 'label' => 'New', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($old->refresh()->is_active)->toBeFalse()->and(CookiePolicyVersion::where('is_active', true)->sole()->version)->toBe('2026.02.01.1');

    Livewire::test(EditCookiePolicyVersion::class, ['record' => $old->getKey()])
        ->fillForm(['label' => 'Old again', 'is_active' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CookiePolicyVersion::where('is_active', true)->sole()->id)->toBe($old->id);
});

it('requires a unique version number on each policy', function () {
    allCompliance();
    CookiePolicyVersion::create(['version' => 'v1', 'label' => 'One']);
    PrivacyPolicyVersion::create(['version' => 'p1', 'label' => 'One']);

    Livewire::test(CreateCookiePolicyVersion::class)->fillForm(['version' => 'v1', 'label' => 'Dup'])->call('create')->assertHasFormErrors(['version']);
    Livewire::test(CreatePrivacyPolicyVersion::class)->fillForm(['version' => 'p1', 'label' => 'Dup'])->call('create')->assertHasFormErrors(['version']);
});

it('publishes privacy policy versions, which can stay active together', function () {
    allCompliance();

    Livewire::test(CreatePrivacyPolicyVersion::class)->fillForm(['version' => 'p1', 'label' => 'One', 'is_active' => true])->call('create')->assertHasNoFormErrors();
    Livewire::test(CreatePrivacyPolicyVersion::class)->fillForm(['version' => 'p2', 'label' => 'Two', 'is_active' => true])->call('create')->assertHasNoFormErrors();

    Livewire::test(ListPrivacyPolicyVersions::class)->assertCountTableRecords(2);
    expect(PrivacyPolicyVersion::where('is_active', true)->count())->toBe(2);
});

// ------------------------------------------------------------------ KPIs

it('counts what is open, late and fulfilled in time', function () {
    $ago = fn (int $days) => now()->subDays($days);
    pendingRequest('export', ['status' => 'received', 'requested_at' => $ago(10)]);
    pendingRequest('erasure', ['status' => 'in_review', 'requested_at' => $ago(40), 'reviewed_at' => $ago(39)]);
    pendingRequest('rectification', ['status' => 'received', 'requested_at' => $ago(40), 'is_on_hold' => true]);
    pendingRequest('restriction', ['status' => 'fulfilled', 'requested_at' => $ago(50), 'fulfilled_at' => $ago(40)]);
    pendingRequest('objection', ['status' => 'fulfilled', 'requested_at' => $ago(60), 'fulfilled_at' => $ago(5)]);
    pendingRequest('export', ['status' => 'fulfilled', 'requested_at' => $ago(200), 'fulfilled_at' => $ago(190)]);

    $m = app(PrivacyKpis::class)->metrics();

    expect($m['open_requests'])->toBe(3)
        ->and($m['open_on_hold'])->toBe(1)
        ->and($m['overdue_reviews'])->toBe(1)
        ->and($m['open_sla_breaches'])->toBe(1)
        ->and($m['fulfilled_total'])->toBe(2)
        ->and($m['fulfilled_within_sla'])->toBe(1)
        ->and($m['fulfilled_sla_rate'])->toBe(50.0);

    config(['filagora-compliance.kpis.sla_days' => 100]);
    expect(app(PrivacyKpis::class)->metrics()['fulfilled_sla_rate'])->toBe(100.0);
});

it('has no fulfilment rate until something was fulfilled', function () {
    expect(app(PrivacyKpis::class)->metrics()['fulfilled_sla_rate'])->toBeNull();
});

it('shows the KPI widget to those who may see requests, while the workflow is on', function () {
    actingAs(TestUser::make('Nobody'));
    expect(PrivacyKpiOverview::canView())->toBeFalse();

    complianceStaff(Abilities::VIEW_REQUESTS);
    expect(PrivacyKpiOverview::canView())->toBeTrue();

    Livewire::test(PrivacyKpiOverview::class)->assertSee('Open requests');

    app(SettingsRegistry::class)->set('enforce_gdpr', false);
    expect(PrivacyKpiOverview::canView())->toBeFalse();
});
