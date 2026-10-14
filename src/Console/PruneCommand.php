<?php

declare(strict_types=1);

namespace Filagora\Compliance\Console;

use Filagora\Compliance\Notifications\PrivacyPruneFailedNotification;
use Filagora\Core\Privacy\RetentionRuleRegistry;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Runs every registered retention rule: each package says what it keeps and for how long, and this
 * deletes what is past it. A rule that fails is reported (and mailed to the DSAR address) and the
 * others still run.
 */
final class PruneCommand extends Command
{
    protected $signature = 'filagora:compliance:prune {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete records that are past their retention period, as every installed package defines it.';

    public function handle(RetentionRuleRegistry $rules, SettingsRegistry $settings): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? 'Running the privacy prune in dry-run mode.' : 'Running the privacy prune.');

        $rows = [];
        $failed = false;

        foreach ($rules->all() as $key => $rule) {
            try {
                $rows[] = [$key, (string) $rule->prune($dryRun)];
            } catch (Throwable $e) {
                $failed = true;
                $rows[] = [$key, 'FAILED: '.$e->getMessage()];
                report($e);
                $this->notifyFailure($settings, $key, $e);
            }
        }

        $this->table(['Rule', $dryRun ? 'Would prune' : 'Pruned'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function notifyFailure(SettingsRegistry $settings, string $rule, Throwable $e): void
    {
        $address = trim($settings->string('dsar_request_email'));

        if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
            Notification::route('mail', $address)->notify(new PrivacyPruneFailedNotification($rule, $e->getMessage()));
        }
    }
}
