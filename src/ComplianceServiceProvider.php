<?php

declare(strict_types=1);

namespace Filagora\Compliance;

use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Navigation\NavigationGroupDefinition;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Entitlements\Registry\Definition;
use Filagora\Entitlements\Registry\EntitlementRegistry;
use Filagora\Entitlements\Services\AuthorizationService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-compliance.php', 'filagora-compliance');
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('compliance', 'Filagora Compliance', 'filagora/filagora-compliance', requires: ['core', 'entitlements']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('compliance', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

        $this->registerNavigationGroup();
        $this->declareEntitlements();
        $this->defineGates();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-compliance');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
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

    private function declareEntitlements(): void
    {
        $registry = $this->app->make(EntitlementRegistry::class);

        foreach (config('filagora-compliance.gates', []) as $entitlement) {
            if (is_string($entitlement) && $entitlement !== '') {
                $registry->define(new Definition($entitlement, wildcardChildren: false));
            }
        }
    }

    private function defineGates(): void
    {
        foreach (config('filagora-compliance.gates', []) as $ability => $entitlement) {
            if (! is_string($ability) || ! is_string($entitlement)) {
                continue;
            }

            Gate::define($ability, fn (?Authenticatable $user): bool => $this->app->make(AuthorizationService::class)->allows($user, $entitlement));
        }
    }
}
