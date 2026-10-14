<?php

declare(strict_types=1);

namespace Filagora\Compliance\Privacy;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\PersonalDataExport;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Contracts\RetentionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Old privacy requests are deleted by how long ago they were settled (windows in
 * `filagora-compliance.retention.privacy_requests`, in days; zero keeps them), together with their
 * export file. A request on legal hold is never deleted. Export files are personal data too, so
 * they go sooner: `delete_export_after_days` after the request was fulfilled, whatever the request's
 * own window, and the request keeps a note that it had an export.
 */
final class PrivacyRequestRetention implements RetentionRule
{
    public function key(): string
    {
        return 'compliance.privacy_requests';
    }

    public function prune(bool $dryRun): int
    {
        $count = 0;

        foreach ($this->windows() as $status => [$column, $fallback, $days]) {
            if ($days <= 0) {
                continue;
            }

            $cutoff = now()->subDays($days);
            $records = $this->query()
                ->where('status', $status)
                ->where(function (Builder $query) use ($column, $fallback, $cutoff): void {
                    $query->where($column, '<', $cutoff);

                    if ($fallback !== null) {
                        $query->orWhere(fn (Builder $inner) => $inner->whereNull($column)->where($fallback, '<', $cutoff));
                    }
                })
                ->get();

            $count += $records->count();

            if (! $dryRun) {
                foreach ($records as $record) {
                    $this->deleteExportFile($record);
                    $record->delete();
                }
            }
        }

        return $count + $this->pruneExportFiles($dryRun);
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: int}> status => [date column, fallback column, days]
     */
    private function windows(): array
    {
        $days = static fn (string $key, int $default): int => (int) config("filagora-compliance.retention.privacy_requests.{$key}", $default);

        return [
            PrivacyRequestStatus::RECEIVED->value => ['requested_at', null, $days('received_stale_days', 365)],
            PrivacyRequestStatus::IN_REVIEW->value => ['reviewed_at', 'requested_at', $days('in_review_stale_days', 730)],
            PrivacyRequestStatus::FULFILLED->value => ['fulfilled_at', 'reviewed_at', $days('fulfilled_days', 1095)],
            PrivacyRequestStatus::REJECTED->value => ['reviewed_at', 'requested_at', $days('rejected_days', 1095)],
        ];
    }

    private function pruneExportFiles(bool $dryRun): int
    {
        $days = (int) config('filagora-compliance.retention.privacy_requests.delete_export_after_days', 30);

        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $records = $this->query()
            ->whereNotNull('export_path')
            ->where('export_path', '!=', '')
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('fulfilled_at', '<', $cutoff)
                    ->orWhere(fn (Builder $inner) => $inner->whereNull('fulfilled_at')->where('requested_at', '<', $cutoff));
            })
            ->get();

        if (! $dryRun) {
            foreach ($records as $record) {
                $this->deleteExportFile($record);
                $record->forceFill(['export_path' => null])->save();
            }
        }

        return $records->count();
    }

    private function deleteExportFile(PrivacyRequest $record): void
    {
        $path = trim((string) $record->export_path);

        if ($path !== '' && Storage::disk(PersonalDataExport::disk())->exists($path)) {
            Storage::disk(PersonalDataExport::disk())->delete($path);
        }
    }

    /**
     * @return Builder<PrivacyRequest>
     */
    private function query(): Builder
    {
        /** @var Builder<PrivacyRequest> */
        return Models::class('privacy_request')::query()->where('is_on_hold', false);
    }
}
