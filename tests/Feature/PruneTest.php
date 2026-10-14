<?php

declare(strict_types=1);

use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Notifications\PrivacyPruneFailedNotification;
use Filagora\Compliance\Privacy\PrivacyRequestRetention;
use Filagora\Compliance\Tests\Support\TestUser;
use Filagora\Core\Contracts\RetentionRule;
use Filagora\Core\Privacy\RetentionRuleRegistry;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function oldRequest(string $status, array $dates, array $attributes = []): PrivacyRequest
{
    $request = PrivacyRequest::create(array_merge(['user_id' => TestUser::make('Old')->id, 'type' => 'export', 'status' => $status], $attributes))->refresh();
    $request->newQuery()->whereKey($request->id)->toBase()->update(array_map(fn ($days) => $days === null ? null : now()->subDays($days), $dates));

    return $request;
}

function pruneRule(bool $dry = false): int
{
    return (new PrivacyRequestRetention)->prune($dry);
}

beforeEach(fn () => Storage::fake('local'));

it('deletes requests settled longer ago than their window, by status', function () {
    $received = oldRequest('received', ['requested_at' => 400]);
    $freshReceived = oldRequest('received', ['requested_at' => 100]);
    $review = oldRequest('in_review', ['requested_at' => 800, 'reviewed_at' => 800]);
    $freshReview = oldRequest('in_review', ['requested_at' => 800, 'reviewed_at' => 100]);
    $fulfilled = oldRequest('fulfilled', ['requested_at' => 1200, 'fulfilled_at' => 1100]);
    $freshFulfilled = oldRequest('fulfilled', ['requested_at' => 1200, 'fulfilled_at' => 1000]);
    $rejected = oldRequest('rejected', ['requested_at' => 1200, 'reviewed_at' => 1100]);

    expect(pruneRule())->toBe(4)
        ->and(PrivacyRequest::query()->pluck('id')->all())->toEqualCanonicalizing([$freshReceived->id, $freshReview->id, $freshFulfilled->id]);
});

it('falls back to the review date, then the request date, when the settled date is missing', function () {
    oldRequest('fulfilled', ['requested_at' => 1200, 'reviewed_at' => 1150, 'fulfilled_at' => null]);
    oldRequest('rejected', ['requested_at' => 1200, 'reviewed_at' => null]);
    oldRequest('in_review', ['requested_at' => 900, 'reviewed_at' => null]);
    $kept = oldRequest('fulfilled', ['requested_at' => 1200, 'reviewed_at' => 500, 'fulfilled_at' => null]);

    expect(pruneRule())->toBe(3)->and(PrivacyRequest::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('never deletes a request on legal hold', function () {
    $held = oldRequest('fulfilled', ['requested_at' => 2000, 'fulfilled_at' => 2000], ['is_on_hold' => true]);
    Storage::disk('local')->put('exports/held.json', '{}');
    $held->update(['export_path' => 'exports/held.json']);

    expect(pruneRule())->toBe(0)
        ->and(PrivacyRequest::count())->toBe(1)
        ->and(Storage::disk('local')->exists('exports/held.json'))->toBeTrue()
        ->and($held->refresh()->export_path)->toBe('exports/held.json');
});

it('keeps a status for ever when its window is zero', function () {
    config(['filagora-compliance.retention.privacy_requests.fulfilled_days' => 0]);
    oldRequest('fulfilled', ['requested_at' => 5000, 'fulfilled_at' => 5000]);

    expect(pruneRule())->toBe(0)->and(PrivacyRequest::count())->toBe(1);
});

it('counts without deleting on a dry run', function () {
    oldRequest('received', ['requested_at' => 400]);
    Storage::disk('local')->put('exports/a.json', '{}');
    oldRequest('fulfilled', ['requested_at' => 40, 'fulfilled_at' => 40], ['export_path' => 'exports/a.json']);

    expect(pruneRule(true))->toBe(2)
        ->and(PrivacyRequest::count())->toBe(2)
        ->and(Storage::disk('local')->exists('exports/a.json'))->toBeTrue();
});

it('deletes the export file with the request', function () {
    Storage::disk('local')->put('exports/old.json', '{}');
    oldRequest('fulfilled', ['requested_at' => 1200, 'fulfilled_at' => 1100], ['export_path' => 'exports/old.json']);

    pruneRule();

    expect(PrivacyRequest::count())->toBe(0)->and(Storage::disk('local')->exists('exports/old.json'))->toBeFalse();
});

it('deletes an export file soon after fulfilment but keeps the request', function () {
    Storage::disk('local')->put('exports/mine.json', '{}');
    Storage::disk('local')->put('exports/fresh.json', '{}');
    $old = oldRequest('fulfilled', ['requested_at' => 60, 'fulfilled_at' => 40], ['export_path' => 'exports/mine.json']);
    $fresh = oldRequest('fulfilled', ['requested_at' => 60, 'fulfilled_at' => 10], ['export_path' => 'exports/fresh.json']);

    expect(pruneRule())->toBe(1)
        ->and(Storage::disk('local')->exists('exports/mine.json'))->toBeFalse()
        ->and(Storage::disk('local')->exists('exports/fresh.json'))->toBeTrue()
        ->and($old->refresh()->export_path)->toBeNull()
        ->and($fresh->refresh()->export_path)->toBe('exports/fresh.json')
        ->and(PrivacyRequest::count())->toBe(2);
});

it('keeps export files for ever when that window is zero, and copes with a file already gone', function () {
    config(['filagora-compliance.retention.privacy_requests.delete_export_after_days' => 0]);
    Storage::disk('local')->put('exports/a.json', '{}');
    oldRequest('fulfilled', ['requested_at' => 60, 'fulfilled_at' => 40], ['export_path' => 'exports/a.json']);
    expect(pruneRule())->toBe(0)->and(Storage::disk('local')->exists('exports/a.json'))->toBeTrue();

    config(['filagora-compliance.retention.privacy_requests.delete_export_after_days' => 30]);
    oldRequest('fulfilled', ['requested_at' => 60, 'fulfilled_at' => 40], ['export_path' => 'exports/missing.json']);
    expect(pruneRule())->toBe(2);
});

it('is registered as a retention rule, and run by the command with every other rule', function () {
    oldRequest('received', ['requested_at' => 400]);
    app(RetentionRuleRegistry::class)->register(new class implements RetentionRule
    {
        public function key(): string
        {
            return 'forums.moderation_logs';
        }

        public function prune(bool $dryRun): int
        {
            return 7;
        }
    });

    expect(array_keys(app(RetentionRuleRegistry::class)->all()))->toContain('compliance.privacy_requests', 'forums.moderation_logs');

    $this->artisan('filagora:compliance:prune')
        ->expectsOutputToContain('Running the privacy prune.')
        ->expectsTable(['Rule', 'Pruned'], [['compliance.privacy_requests', '1'], ['forums.moderation_logs', '7']])
        ->assertExitCode(0);

    expect(PrivacyRequest::count())->toBe(0);
});

it('reports what a dry run would do and deletes nothing', function () {
    oldRequest('received', ['requested_at' => 400]);

    $this->artisan('filagora:compliance:prune', ['--dry-run' => true])
        ->expectsOutputToContain('dry-run mode')
        ->expectsTable(['Rule', 'Would prune'], [['compliance.privacy_requests', '1']])
        ->assertExitCode(0);

    expect(PrivacyRequest::count())->toBe(1);
});

it('reports a failing rule, mails the DSAR address, and still runs the others', function () {
    Notification::fake();
    app(SettingsRegistry::class)->set('dsar_request_email', 'privacy@example.test');
    app(RetentionRuleRegistry::class)->register(new class implements RetentionRule
    {
        public function key(): string
        {
            return 'a.broken';
        }

        public function prune(bool $dryRun): int
        {
            throw new RuntimeException('disk gone');
        }
    });
    oldRequest('received', ['requested_at' => 400]);

    $this->artisan('filagora:compliance:prune')->assertExitCode(1);

    expect(PrivacyRequest::count())->toBe(0);
    Notification::assertSentOnDemand(PrivacyPruneFailedNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'privacy@example.test');
});

it('says nothing to anyone when no DSAR address is set', function () {
    Notification::fake();
    app(RetentionRuleRegistry::class)->register(new class implements RetentionRule
    {
        public function key(): string
        {
            return 'a.broken';
        }

        public function prune(bool $dryRun): int
        {
            throw new RuntimeException('disk gone');
        }
    });

    $this->artisan('filagora:compliance:prune')->assertExitCode(1);

    Notification::assertSentOnDemandTimes(PrivacyPruneFailedNotification::class, 0);
});

it('is scheduled daily at the configured time', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'filagora:compliance:prune'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('15 2 * * *');
});
