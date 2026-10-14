<?php

declare(strict_types=1);

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserKey;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The cookie consent, privacy policy and privacy request tables, in the shape of the forum the
 * package was extracted from. Names that would pass MariaDB's 64 character limit once the table
 * prefix is added are written out (the names that forum has), unprefixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::name('cookie_consent_groups'), function (Blueprint $table): void {
            $table->id();
            $table->string('group_key', 80)->unique();
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('default_enabled')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create(Tables::name('cookie_consent_cookies'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('cookie_name', 128)->unique();
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->string('provider')->nullable();
            $table->string('retention_period', 80)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on(Tables::name('cookie_consent_groups'))->cascadeOnDelete();
        });

        Schema::create(Tables::name('cookie_policy_versions'), function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('label', 120);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create(Tables::name('user_cookie_consents'), function (Blueprint $table): void {
            $table->id();
            UserKey::column($table, 'user_id')->nullable()->unique();
            $table->unsignedBigInteger('cookie_policy_version_id')->nullable();
            $table->json('consent');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('consented_at')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on(UserModel::table())->cascadeOnDelete();
            $table->foreign('cookie_policy_version_id')->references('id')->on(Tables::name('cookie_policy_versions'))->nullOnDelete();
        });

        Schema::create(Tables::name('privacy_policy_versions'), function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('label', 160);
            $table->text('summary')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create(Tables::name('user_privacy_policy_acceptances'), function (Blueprint $table): void {
            $table->id();
            UserKey::column($table, 'user_id');
            $table->unsignedBigInteger('privacy_policy_version_id');
            $table->timestamp('accepted_at');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'privacy_policy_version_id'], 'user_privacy_policy_unique');
            $table->index(['user_id', 'accepted_at'], 'user_privacy_policy_acceptances_user_id_accepted_at_index');
            $table->index('accepted_at');

            $table->foreign('user_id', 'upa_user_fk')->references('id')->on(UserModel::table())->cascadeOnDelete();
            $table->foreign('privacy_policy_version_id', 'upa_ppv_fk')->references('id')->on(Tables::name('privacy_policy_versions'))->cascadeOnDelete();
        });

        Schema::create(Tables::name('privacy_requests'), function (Blueprint $table): void {
            $table->id();
            UserKey::column($table, 'user_id');
            $table->string('type', 24)->index();
            $table->string('status', 24)->default('received')->index();
            $table->text('request_details')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('export_path', 1024)->nullable();
            $table->boolean('is_on_hold')->default(false)->index();
            $table->text('hold_reason')->nullable();
            $table->timestamp('held_at')->nullable()->index();
            $table->timestamp('requested_at')->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamp('fulfilled_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            UserKey::column($table, 'reviewer_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'requested_at']);
            $table->index(['status', 'requested_at']);
            $table->index(['reviewer_id', 'reviewed_at'], 'privacy_requests_reviewer_reviewed_at_idx');
            $table->index(['is_on_hold', 'requested_at'], 'privacy_requests_hold_requested_idx');

            $table->foreign('user_id')->references('id')->on(UserModel::table())->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on(UserModel::table())->nullOnDelete();
        });

        Schema::create(Tables::name('privacy_operation_audits'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('privacy_request_id');
            UserKey::column($table, 'request_user_id');
            UserKey::column($table, 'actor_user_id')->nullable();
            $table->string('action', 64)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['privacy_request_id', 'created_at'], 'privacy_operation_audits_privacy_request_id_created_at_index');
            $table->index(['request_user_id', 'created_at'], 'privacy_operation_audits_request_user_id_created_at_index');
            $table->index(['actor_user_id', 'created_at']);

            $table->foreign('privacy_request_id')->references('id')->on(Tables::name('privacy_requests'))->cascadeOnDelete();
            $table->foreign('request_user_id')->references('id')->on(UserModel::table())->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on(UserModel::table())->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('privacy_operation_audits'));
        Schema::dropIfExists(Tables::name('privacy_requests'));
        Schema::dropIfExists(Tables::name('user_privacy_policy_acceptances'));
        Schema::dropIfExists(Tables::name('privacy_policy_versions'));
        Schema::dropIfExists(Tables::name('user_cookie_consents'));
        Schema::dropIfExists(Tables::name('cookie_policy_versions'));
        Schema::dropIfExists(Tables::name('cookie_consent_cookies'));
        Schema::dropIfExists(Tables::name('cookie_consent_groups'));
    }
};
