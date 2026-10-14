<?php

declare(strict_types=1);

namespace Filagora\Compliance;

use Filagora\Cms\Events\CmsPagePublished;
use Filagora\Compliance\Console\PruneCommand;
use Filagora\Compliance\Contracts\UserAnonymizer;
use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Compliance\Listeners\SendPrivacyMail;
use Filagora\Compliance\Listeners\VersionCookiePolicyOnPagePublished;
use Filagora\Compliance\Privacy\ComplianceDataContributor;
use Filagora\Compliance\Privacy\PrivacyRequestRetention;
use Filagora\Compliance\Services\CookieConsent;
use Filagora\Compliance\Services\CookiePolicyVersions;
use Filagora\Compliance\Services\PersonalDataErasure;
use Filagora\Compliance\Services\PersonalDataExport;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Compliance\Services\PrivacyAudit;
use Filagora\Compliance\Services\PrivacyRequests;
use Filagora\Compliance\Support\ConfiguredColumnsAnonymizer;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Navigation\NavigationGroupDefinition;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Core\Privacy\PrivacyContributorRegistry;
use Filagora\Core\Privacy\RetentionRuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Core\Settings\SettingDefinition;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Core\Settings\SettingType;
use Filagora\Core\Support\GateAbilities;
use Filagora\Entitlements\Registry\Definition;
use Filagora\Entitlements\Registry\EntitlementRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-compliance.php', 'filagora-compliance');

        $this->app->singleton(CookieConsent::class);
        $this->app->singleton(PolicyAcceptance::class);
        $this->app->singleton(CookiePolicyVersions::class);
        $this->app->singleton(PrivacyAudit::class);
        $this->app->singleton(PersonalDataExport::class);
        $this->app->singleton(PersonalDataErasure::class);
        $this->app->singleton(PrivacyRequests::class);
        $this->app->bindIf(UserAnonymizer::class, ConfiguredColumnsAnonymizer::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('compliance', 'Filagora Compliance', 'filagora/filagora-compliance', requires: ['core']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('compliance', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

        $this->registerNavigationGroup();
        $this->declareEntitlements();
        $this->defineGates();
        $this->declareSettings();
        $this->app->make(PrivacyContributorRegistry::class)->register(new ComplianceDataContributor);
        $this->app->make(RetentionRuleRegistry::class)->register(new PrivacyRequestRetention);

        $this->app->booted(function (): void {
            if (config('filagora-compliance.schedule.enabled', true)) {
                $this->app->make(Schedule::class)->command('filagora:compliance:prune')->dailyAt((string) config('filagora-compliance.schedule.daily_at', '02:15'));
            }
        });

        Event::listen(PrivacyRequestSubmitted::class, [SendPrivacyMail::class, 'submitted']);
        Event::listen(PrivacyRequestStatusChanged::class, [SendPrivacyMail::class, 'statusChanged']);
        Event::listen(PrivacyReplySent::class, [SendPrivacyMail::class, 'replied']);
        $this->forgetCachesOnChange();

        if (class_exists(CmsPagePublished::class)) {
            Event::listen(CmsPagePublished::class, VersionCookiePolicyOnPagePublished::class);
        }

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-compliance');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class]);

            $this->publishes([
                __DIR__.'/../config/filagora-compliance.php' => config_path('filagora-compliance.php'),
            ], 'filagora-compliance-config');
        }
    }

    private function registerNavigationGroup(): void
    {
        $this->app->make(NavigationGroupRegistry::class)->register(new NavigationGroupDefinition(
            key: 'compliance',
            label: fn (): string => __('filagora-compliance::messages.navigation_groups.compliance'),
            icon: 'heroicon-o-shield-check',
            sort: 85,
        ));
    }

    /**
     * With `filagora-entitlements` installed, the keys the abilities map to are declared so
     * `filagora:entitlements:sync` creates their definitions. Without it there is nothing to
     * declare them to.
     */
    private function declareEntitlements(): void
    {
        if (! class_exists(EntitlementRegistry::class) || ! $this->app->bound(EntitlementRegistry::class)) {
            return;
        }

        $registry = $this->app->make(EntitlementRegistry::class);

        foreach (GateAbilities::keys('filagora-compliance.gates') as $entitlement) {
            $registry->define(new Definition($entitlement, wildcardChildren: false));
        }
    }

    private function defineGates(): void
    {
        GateAbilities::define('filagora-compliance.gates');
    }

    /**
     * The settings the services read, with their defaults (keys and types match the live site).
     */
    private function declareSettings(): void
    {
        $settings = $this->app->make(SettingsRegistry::class);

        $settings->define(new SettingDefinition(
            CookieConsent::SETTING,
            SettingType::Boolean,
            true,
            label: 'Enable Cookie Consent Workflow',
            description: 'When off, no cookie consent is asked for or stored.',
        ));

        $settings->define(new SettingDefinition(
            PolicyAcceptance::SETTING,
            SettingType::Boolean,
            true,
            label: 'Enforce GDPR Workflow',
            description: 'When off, users are not asked to accept the privacy policy and privacy requests are closed.',
        ));

        $settings->define(new SettingDefinition(
            'dsar_request_email',
            SettingType::String,
            '',
            label: 'DSAR Request Notification Email',
            description: 'Staff address told about each new privacy request. Leave empty to tell no one.',
        ));

        $settings->define(new SettingDefinition(
            VersionCookiePolicyOnPagePublished::SETTING,
            SettingType::Page,
            '/cookie-policy',
            label: 'Cookie Policy Page',
            description: 'A route beginning with / or a CMS page slug. Publishing this page creates a new cookie policy version.',
        ));

        $settings->define(new SettingDefinition(
            'privacy_page',
            SettingType::Page,
            '/privacy',
            label: 'Privacy Policy Page',
            description: 'A route beginning with / or a CMS page slug.',
        ));
    }

    /**
     * The cached id of the active privacy policy version is forgotten as soon as a version changes.
     */
    private function forgetCachesOnChange(): void
    {
        $forgetActive = static fn () => Cache::forget(PolicyAcceptance::activeCacheKey());

        Models::class('privacy_policy_version')::saved($forgetActive);
        Models::class('privacy_policy_version')::deleted($forgetActive);
    }
}
