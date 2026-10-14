<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Widgets;

use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Compliance\Services\PrivacyKpis;
use Filagora\Compliance\Support\Abilities;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/**
 * Privacy request numbers for the dashboard: the share fulfilled in time, what is open, and what
 * is waiting too long for a first look. Shown to those who may see privacy requests, and only
 * while the privacy workflow is on.
 */
class PrivacyKpiOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return app(PolicyAcceptance::class)->isEnforced() && Gate::allows(Abilities::VIEW_REQUESTS);
    }

    protected function getStats(): array
    {
        $m = app(PrivacyKpis::class)->metrics();
        $rate = $m['fulfilled_sla_rate'];
        $t = static fn (string $key, array $replace = []): string => __("filagora-compliance::ui.kpis.{$key}", $replace);

        return [
            Stat::make($t('sla_title', ['days' => $m['sla_days']]), $rate === null ? $t('sla_none') : sprintf('%.1f%%', $rate))
                ->description($t('sla_description', ['within' => $m['fulfilled_within_sla'], 'total' => $m['fulfilled_total'], 'window' => $m['sla_window_days'], 'breaches' => $m['open_sla_breaches']]))
                ->color($rate === null || $rate >= 95.0 ? 'success' : ($rate >= 80.0 ? 'warning' : 'danger')),
            Stat::make($t('open_title'), (string) $m['open_requests'])
                ->description($t('open_description', ['count' => $m['open_on_hold']]))
                ->color($m['open_requests'] > 0 ? 'warning' : 'success'),
            Stat::make($t('overdue_title'), (string) $m['overdue_reviews'])
                ->description($t('overdue_description', ['days' => $m['overdue_review_days']]))
                ->color($m['overdue_reviews'] > 0 ? 'danger' : 'success'),
        ];
    }
}
