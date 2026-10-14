<?php

declare(strict_types=1);

use Filagora\Core\Schema\Adopter;
use Filagora\Core\Schema\AdoptionStatus;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Core\Schema\TableShape;
use Filagora\Core\Support\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The tables must match the site they were extracted from, so an existing site can adopt them
 * as they are. The shipped snapshot is that shape (checked against the real table definitions);
 * these tests keep the migration in step with it and pin the details that matter most.
 */

const COMPLIANCE_TABLES = [
    'cookie_consent_groups', 'cookie_consent_cookies', 'cookie_policy_versions', 'user_cookie_consents',
    'privacy_policy_versions', 'user_privacy_policy_acceptances', 'privacy_requests', 'privacy_operation_audits',
];
const COMPLIANCE_MIGRATION = '2026_10_14_000000_create_filagora_compliance_tables';

function complianceMigration(): object
{
    return require __DIR__.'/../../database/migrations/'.COMPLIANCE_MIGRATION.'.php';
}

/**
 * The tables as an existing site has them: no prefix, migration not recorded. The constraint
 * names of the acceptance table do not carry the prefix, so the prefixed set has to go first;
 * afterEach() puts it back.
 */
function existingComplianceTables(): void
{
    complianceMigration()->down();
    config(['filagora.table_prefix' => '']);
    complianceMigration()->down();
    complianceMigration()->up();
    DB::table('migrations')->where('migration', COMPLIANCE_MIGRATION)->delete();
}

afterEach(function () {
    foreach (array_reverse(COMPLIANCE_TABLES) as $table) {
        Schema::dropIfExists($table);
    }

    config(['filagora.table_prefix' => 'filagora_']);

    if (! Schema::hasTable(Tables::name('privacy_requests'))) {
        complianceMigration()->up();
    }
});

it('ships a snapshot of the eight tables that matches the migration, column order included', function () {
    $snapshot = app(SchemaRegistry::class)->get('compliance');

    expect($snapshot)->not->toBeNull()
        ->and(array_keys($snapshot->tables))->toBe(COMPLIANCE_TABLES)
        ->and($snapshot->migrationNames())->toBe([COMPLIANCE_MIGRATION]);

    foreach ($snapshot->tables as $base => $expected) {
        expect(TableShape::inspect($base)->differencesFrom($expected, compareColumnOrder: true))->toBe([], $base);
    }
});

it('keeps the constraint and index names the live site has, even where the prefix would make them too long', function () {
    $acceptances = TableShape::inspect('user_privacy_policy_acceptances');
    $audits = TableShape::inspect('privacy_operation_audits');
    $requests = TableShape::inspect('privacy_requests');

    expect(array_keys($acceptances->foreignKeys))->toEqualCanonicalizing(['upa_ppv_fk', 'upa_user_fk'])
        ->and($acceptances->indexes['user_privacy_policy_unique']['unique'])->toBeTrue()
        ->and($acceptances->indexes)->toHaveKey('user_privacy_policy_acceptances_user_id_accepted_at_index')
        ->and($audits->indexes['privacy_operation_audits_privacy_request_id_created_at_index']['columns'])->toBe(['privacy_request_id', 'created_at'])
        ->and($audits->indexes['privacy_operation_audits_request_user_id_created_at_index']['columns'])->toBe(['request_user_id', 'created_at'])
        ->and($requests->indexes['privacy_requests_hold_requested_idx']['columns'])->toBe(['is_on_hold', 'requested_at']);
});

it('keeps the uniqueness and defaults the site relies on', function () {
    $groups = TableShape::inspect('cookie_consent_groups');
    $requests = TableShape::inspect('privacy_requests');
    $consents = TableShape::inspect('user_cookie_consents');

    expect($groups->indexes['cookie_consent_groups_group_key_unique']['unique'])->toBeTrue()
        ->and(TableShape::inspect('cookie_consent_cookies')->indexes['cookie_consent_cookies_cookie_name_unique']['unique'])->toBeTrue()
        ->and(TableShape::inspect('cookie_policy_versions')->indexes['cookie_policy_versions_version_unique']['unique'])->toBeTrue()
        ->and($consents->indexes['user_cookie_consents_user_id_unique']['unique'])->toBeTrue()
        ->and($consents->columns['user_id']['nullable'])->toBeTrue()
        ->and($requests->columns['status']['default'])->toBe('received')
        ->and($requests->columns['is_on_hold']['default'])->toBe('0')
        ->and($groups->columns['is_enabled']['default'])->toBe('1');
});

it('adopts the compliance tables of an existing site and records the migration', function () {
    existingComplianceTables();

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('compliance'));

    expect($plan->status)->toBe(AdoptionStatus::Adoptable, implode('; ', $plan->differences))
        ->and($plan->pendingMigrations)->toBe([COMPLIANCE_MIGRATION]);

    $this->artisan('filagora:adopt', ['--package' => 'compliance'])->expectsOutputToContain('Recorded 1 migration(s) as run.')->assertExitCode(0);

    expect(DB::table('migrations')->where('migration', COMPLIANCE_MIGRATION)->exists())->toBeTrue();
});

it('refuses a site whose table lost an index, and one that has only some of the tables', function () {
    existingComplianceTables();
    Schema::table('privacy_requests', fn ($table) => $table->dropIndex('privacy_requests_hold_requested_idx'));

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('compliance'));
    expect($plan->status)->toBe(AdoptionStatus::Mismatch)
        ->and($plan->differences)->toContain('privacy_requests: index privacy_requests_hold_requested_idx is missing');

    Schema::drop('privacy_operation_audits');
    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('compliance'));
    expect($plan->status)->toBe(AdoptionStatus::Partial);
});

it('is already adopted on a site that ran the package migration', function () {
    expect(app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('compliance'))->status)->toBe(AdoptionStatus::AlreadyAdopted)
        ->and(Tables::name('privacy_requests'))->toBe('filagora_privacy_requests');
});
