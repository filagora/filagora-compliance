<?php

declare(strict_types=1);

namespace Filagora\Compliance;

use Filagora\Compliance\Filament\Resources\CookieConsentGroups\CookieConsentGroupResource;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\CookiePolicyVersionResource;
use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\PrivacyOperationAuditResource;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\PrivacyPolicyVersionResource;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filagora\Compliance\Filament\Widgets\PrivacyKpiOverview;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilagoraCompliancePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filagora-compliance';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                PrivacyRequestResource::class,
                CookieConsentGroupResource::class,
                CookiePolicyVersionResource::class,
                PrivacyPolicyVersionResource::class,
                PrivacyOperationAuditResource::class,
            ])
            ->widgets([PrivacyKpiOverview::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
