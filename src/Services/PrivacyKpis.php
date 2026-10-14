<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Support\Models;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers a compliance officer watches: open requests, those waiting too long for review,
 * open requests past the deadline, and the share fulfilled within it. The deadline, the review
 * wait and the look-back window are in `filagora-compliance.kpis`.
 */
class PrivacyKpis
{
    /**
     * @return array{sla_days: int, overdue_review_days: int, sla_window_days: int, open_requests: int, open_on_hold: int, overdue_reviews: int, open_sla_breaches: int, fulfilled_total: int, fulfilled_within_sla: int, fulfilled_sla_rate: float|null}
     */
    public function metrics(): array
    {
        $slaDays = max(1, (int) config('filagora-compliance.kpis.sla_days', 30));
        $overdueReviewDays = max(1, (int) config('filagora-compliance.kpis.overdue_review_days', 7));
        $windowDays = max(1, (int) config('filagora-compliance.kpis.sla_window_days', 90));

        $open = fn (): Builder => $this->requests()->whereIn('status', [PrivacyRequestStatus::RECEIVED->value, PrivacyRequestStatus::IN_REVIEW->value]);

        $fulfilled = $this->requests()
            ->where('status', PrivacyRequestStatus::FULFILLED->value)
            ->whereNotNull('fulfilled_at')
            ->where('requested_at', '>=', now()->subDays($windowDays))
            ->get(['requested_at', 'fulfilled_at']);

        $within = $fulfilled->filter(fn (PrivacyRequest $request): bool => $request->requested_at->diffInDays($request->fulfilled_at) <= $slaDays)->count();

        return [
            'sla_days' => $slaDays,
            'overdue_review_days' => $overdueReviewDays,
            'sla_window_days' => $windowDays,
            'open_requests' => $open()->count(),
            'open_on_hold' => $open()->where('is_on_hold', true)->count(),
            'overdue_reviews' => $this->requests()
                ->where('status', PrivacyRequestStatus::RECEIVED->value)
                ->where('is_on_hold', false)
                ->where('requested_at', '<=', now()->subDays($overdueReviewDays))
                ->count(),
            'open_sla_breaches' => $open()->where('is_on_hold', false)->where('requested_at', '<=', now()->subDays($slaDays))->count(),
            'fulfilled_total' => $fulfilled->count(),
            'fulfilled_within_sla' => $within,
            'fulfilled_sla_rate' => $fulfilled->isEmpty() ? null : round(($within / $fulfilled->count()) * 100, 1),
        ];
    }

    /**
     * @return Builder<PrivacyRequest>
     */
    private function requests(): Builder
    {
        /** @var Builder<PrivacyRequest> */
        return Models::class('privacy_request')::query();
    }
}
