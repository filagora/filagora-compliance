<?php

declare(strict_types=1);

namespace Filagora\Compliance\Tests\Support;

use Filagora\Compliance\FilagoraCompliancePlugin;
use Filagora\Core\FilagoraCorePlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A panel for a host that has no entitlements package: core and compliance only.
 */
class NoEntitlementsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilagoraCorePlugin::make())
            ->plugin(FilagoraCompliancePlugin::make());
    }
}
