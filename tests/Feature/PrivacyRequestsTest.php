<?php

declare(strict_types=1);

use Filagora\Compliance\Contracts\UserAnonymizer;
use Filagora\Compliance\Enums\PrivacyRequestRefusal;
use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Compliance\Exceptions\PrivacyRequestRefused;
use Filagora\Compliance\Models\PrivacyOperationAudit;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Models\UserCookieConsent;
use Filagora\Compliance\Notifications\PrivacyRequestReplyNotification;
use Filagora\Compliance\Notifications\PrivacyRequestStatusNotification;
use Filagora\Compliance\Notifications\PrivacyRequestSubmittedNotification;
use Filagora\Compliance\Notifications\PrivacyRequestSubmittedStaffNotification;
use Filagora\Compliance\Services\PersonalDataErasure;
use Filagora\Compliance\Services\PrivacyRequests;
use Filagora\Compliance\Tests\Support\TestUser;
use Filagora\Core\Contracts\PrivacyContributor;
use Filagora\Core\Privacy\PrivacyContributorRegistry;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function requests(): PrivacyRequests
{
    return app(PrivacyRequests::class);
}

function refusedWith(callable $call): ?PrivacyRequestRefusal
{
    try {
        $call();
    } catch (PrivacyRequestRefused $e) {
        return $e->reason;
    }

    return null;
}

function openRequest(TestUser $user, string $type = 'export', array $attributes = []): PrivacyRequest
{
    return PrivacyRequest::create(array_merge(['user_id' => $user->id, 'type' => $type, 'request_details' => 'Please'], $attributes))->refresh();
}

function auditsOf(PrivacyRequest $request): array
{
    return PrivacyOperationAudit::query()->where('privacy_request_id', $request->id)->orderBy('id')->pluck('action')->all();
}

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
});

// ------------------------------------------------------------------ submitting

it('takes a request, trimmed, received, and announces it', function () {
    Event::fake([PrivacyRequestSubmitted::class]);
    $user = TestUser::make('Requester');

    $request = requests()->submit($user, 'export', "  Please send my data \n");

    expect($request->status)->toBe(PrivacyRequestStatus::RECEIVED)
        ->and($request->type)->toBe(PrivacyRequestType::EXPORT)
        ->and($request->request_details)->toBe('Please send my data')
        ->and($request->user_id)->toBe($user->id);
    Event::assertDispatched(PrivacyRequestSubmitted::class, fn ($event) => $event->request->is($request));
});

it('refuses what it should, with the reason', function () {
    $user = TestUser::make('Requester');

    expect(refusedWith(fn () => requests()->submit($user, 'access', 'x')))->toBe(PrivacyRequestRefusal::UNKNOWN_TYPE)
        ->and(refusedWith(fn () => requests()->submit($user, 'nonsense', 'x')))->toBe(PrivacyRequestRefusal::UNKNOWN_TYPE)
        ->and(refusedWith(fn () => requests()->submit($user, 'export', "  \n")))->toBe(PrivacyRequestRefusal::DETAILS_REQUIRED)
        ->and(refusedWith(fn () => requests()->submit($user, 'export', null)))->toBe(PrivacyRequestRefusal::DETAILS_REQUIRED);

    requests()->submit($user, 'export', 'First');
    expect(refusedWith(fn () => requests()->submit($user, 'export', 'Again')))->toBe(PrivacyRequestRefusal::OPEN_REQUEST_OF_TYPE)
        ->and(refusedWith(fn () => requests()->submit($user, 'erasure', 'Different type')))->toBeNull();

    expect(PrivacyRequest::count())->toBe(2)
        ->and(PrivacyRequestRefusal::OPEN_REQUEST_OF_TYPE->message())->toBe('You already have an open request of this type.');
});

it('counts a request in review as still open', function () {
    $user = TestUser::make('Requester');
    openRequest($user, 'export', ['status' => 'in_review']);

    expect(refusedWith(fn () => requests()->submit($user, 'export', 'Again')))->toBe(PrivacyRequestRefusal::OPEN_REQUEST_OF_TYPE);
});

it('lets someone ask again once their earlier request of that type is closed', function () {
    $user = TestUser::make('Requester');
    $first = requests()->submit($user, 'export', 'First');
    requests()->changeStatus($first, PrivacyRequestStatus::REJECTED);

    expect(requests()->submit($user, 'export', 'Second'))->toBeInstanceOf(PrivacyRequest::class);
});

it('takes nothing once the account was erased, and nothing while the workflow is off', function () {
    $erased = TestUser::make('Erased');
    openRequest($erased, 'erasure', ['status' => 'fulfilled']);
    expect(refusedWith(fn () => requests()->submit($erased, 'export', 'x')))->toBe(PrivacyRequestRefusal::ERASURE_ALREADY_FULFILLED);

    app(SettingsRegistry::class)->set('enforce_gdpr', false);
    expect(refusedWith(fn () => requests()->submit(TestUser::make('Other'), 'export', 'x')))->toBe(PrivacyRequestRefusal::ENFORCEMENT_OFF);
});

it('cuts very long details to the limit', function () {
    $request = requests()->submit(TestUser::make('Requester'), 'export', str_repeat('é', 6000));

    expect(mb_strlen($request->request_details))->toBe(5000);
});

// ------------------------------------------------------------------ status

it('keeps dates and reviewer consistent as the status changes', function () {
    $staff = TestUser::make('Staff');
    $request = openRequest(TestUser::make('Requester'));

    $request = requests()->changeStatus($request, PrivacyRequestStatus::IN_REVIEW, $staff);
    expect($request->reviewed_at)->not->toBeNull()->and($request->reviewer_id)->toBe($staff->id)->and($request->fulfilled_at)->toBeNull();

    $reviewedAt = $request->reviewed_at;
    $this->travel(2)->hours();
    $request = requests()->changeStatus($request, PrivacyRequestStatus::FULFILLED, $staff);
    expect($request->fulfilled_at)->not->toBeNull()->and($request->rejected_at)->toBeNull()->and($request->reviewed_at->equalTo($reviewedAt))->toBeTrue();

    $request = requests()->changeStatus($request, PrivacyRequestStatus::REJECTED, $staff);
    expect($request->rejected_at)->not->toBeNull()->and($request->fulfilled_at)->toBeNull();

    $request = requests()->changeStatus($request, PrivacyRequestStatus::RECEIVED);
    expect($request->rejected_at)->toBeNull()->and($request->fulfilled_at)->toBeNull()->and($request->reviewer_id)->toBe($staff->id);
});

it('announces a change of status once, and nothing when it is unchanged', function () {
    Event::fake([PrivacyRequestStatusChanged::class]);
    $request = openRequest(TestUser::make('Requester'));

    requests()->changeStatus($request, PrivacyRequestStatus::IN_REVIEW);
    requests()->changeStatus($request, PrivacyRequestStatus::IN_REVIEW);

    Event::assertDispatchedTimes(PrivacyRequestStatusChanged::class, 1);
    Event::assertDispatched(PrivacyRequestStatusChanged::class, fn ($e) => $e->from === PrivacyRequestStatus::RECEIVED && $e->to === PrivacyRequestStatus::IN_REVIEW);
});

it('audits a request fulfilled by hand, once, and only when nothing else was audited', function () {
    $staff = TestUser::make('Staff');
    $request = openRequest(TestUser::make('Requester'));

    requests()->changeStatus($request, PrivacyRequestStatus::FULFILLED, $staff);
    expect(auditsOf($request))->toBe(['request_fulfilled_status_update']);

    $other = openRequest(TestUser::make('Other'));
    requests()->reply($other, 'Hello');
    requests()->changeStatus($other, PrivacyRequestStatus::FULFILLED, $staff);
    expect(auditsOf($other))->toBe(['reply_sent']);
});

// ------------------------------------------------------------------ hold, reject, reply

it('puts a request on hold and releases it, with audits', function () {
    $staff = TestUser::make('Staff');
    $request = openRequest(TestUser::make('Requester'));

    requests()->hold($request, ' Legal matter ', $staff);
    expect($request->refresh()->is_on_hold)->toBeTrue()->and($request->hold_reason)->toBe('Legal matter')->and($request->held_at)->not->toBeNull();

    requests()->release($request, $staff);
    expect($request->refresh()->is_on_hold)->toBeFalse()->and($request->hold_reason)->toBeNull()->and($request->held_at)->toBeNull()
        ->and(auditsOf($request))->toBe(['hold_placed', 'hold_released']);
});

it('rejects a request with a note and an audit', function () {
    $staff = TestUser::make('Staff');
    $request = openRequest(TestUser::make('Requester'));

    requests()->reject($request, 'Not verified', $staff);

    expect($request->refresh()->status)->toBe(PrivacyRequestStatus::REJECTED)
        ->and($request->rejected_at)->not->toBeNull()
        ->and($request->admin_notes)->toContain('Rejected: Not verified')
        ->and(auditsOf($request))->toBe(['request_rejected']);
});

it('replies: noted, audited, announced and mailed', function () {
    Event::fake([PrivacyReplySent::class]);
    $staff = TestUser::make('Staff');
    $user = TestUser::make('Requester');
    $request = openRequest($user);

    requests()->reply($request, 'We are on it', $staff);

    expect($request->refresh()->admin_notes)->toContain('Reply sent to requester: We are on it')
        ->and($request->replies->pluck('meta.message')->all())->toBe(['We are on it'])
        ->and($request->replies->first()->actor_user_id)->toBe($staff->id);
    Event::assertDispatched(PrivacyReplySent::class, fn ($e) => $e->message === 'We are on it');
});

// ------------------------------------------------------------------ export

it('fulfils an export: file written from every contributor, request closed, audited', function () {
    app(PrivacyContributorRegistry::class)->register(new class implements PrivacyContributor
    {
        public function key(): string
        {
            return 'forums';
        }

        public function export(Model $user): array
        {
            return ['threads' => [['title' => 'Mine']]];
        }

        public function erase(Model $user): void {}
    });
    $staff = TestUser::make('Staff');
    $user = TestUser::make('Requester');
    UserCookieConsent::create(['user_id' => $user->id, 'consent' => ['necessary' => true], 'consented_at' => now()]);
    $request = openRequest($user);

    $request = requests()->fulfilExport($request, $staff);

    $file = json_decode(Storage::disk('local')->get($request->export_path), true);

    expect($request->status)->toBe(PrivacyRequestStatus::FULFILLED)
        ->and($request->export_path)->toStartWith('exports/privacy/user-'.$user->id.'/privacy-export-')
        ->and($request->reviewer_id)->toBe($staff->id)
        ->and($request->admin_notes)->toContain('User export generated at')
        ->and(auditsOf($request))->toBe(['export_fulfilled'])
        ->and(array_keys($file))->toContain('generated_at', 'user', 'forums', 'compliance')
        ->and($file['user']['email'])->toBe($user->email)
        ->and($file['user'])->not->toHaveKey('password')
        ->and($file['forums']['threads'][0]['title'])->toBe('Mine')
        ->and($file['compliance']['cookie_consent'][0]['consent'])->toBe(['necessary' => true]);
});

it('mails the requester a signed download link when the host gave a route for it', function () {
    Route::get('/exports/{privacyRequest}', fn () => '')->name('exports.download');
    app('router')->getRoutes()->refreshNameLookups();
    config(['filagora-compliance.routes.export_download' => 'exports.download']);
    $user = TestUser::make('Requester');

    requests()->fulfilExport(openRequest($user), TestUser::make('Staff'));

    Notification::assertSentTo($user, PrivacyRequestStatusNotification::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);

        return str_contains((string) $mail->actionUrl, '/exports/') && str_contains((string) $mail->actionUrl, 'signature=');
    });
});

it('leaves the link out when there is no route for it', function () {
    $user = TestUser::make('Requester');

    requests()->fulfilExport(openRequest($user), TestUser::make('Staff'));

    Notification::assertSentTo($user, PrivacyRequestStatusNotification::class, fn ($notification) => $notification->toMail($user)->actionUrl === null);
});

it('will not fulfil the wrong type, or anything on hold', function () {
    $user = TestUser::make('Requester');
    $erasure = openRequest($user, 'erasure');
    $held = openRequest(TestUser::make('Held'), 'export', ['is_on_hold' => true]);
    $heldErasure = openRequest(TestUser::make('HeldToo'), 'erasure', ['is_on_hold' => true]);

    expect(refusedWith(fn () => requests()->fulfilExport($erasure)))->toBe(PrivacyRequestRefusal::WRONG_TYPE)
        ->and(refusedWith(fn () => requests()->fulfilErasure(openRequest(TestUser::make('X'), 'export'))))->toBe(PrivacyRequestRefusal::WRONG_TYPE)
        ->and(refusedWith(fn () => requests()->fulfilExport($held)))->toBe(PrivacyRequestRefusal::ON_HOLD)
        ->and(refusedWith(fn () => requests()->fulfilErasure($heldErasure)))->toBe(PrivacyRequestRefusal::ON_HOLD)
        ->and($held->refresh()->status)->toBe(PrivacyRequestStatus::RECEIVED);
});

// ------------------------------------------------------------------ erasure

it('fulfils an erasure: every contributor erases, then the user is anonymised, and the request closes', function () {
    $erased = [];
    app(PrivacyContributorRegistry::class)->register(new class($erased) implements PrivacyContributor
    {
        public function __construct(public array &$erased) {}

        public function key(): string
        {
            return 'forums';
        }

        public function export(Model $user): array
        {
            return [];
        }

        public function erase(Model $user): void
        {
            $this->erased[] = $user->getKey();
        }
    });
    $user = TestUser::make('Requester');
    $originalEmail = $user->email;
    UserCookieConsent::create(['user_id' => $user->id, 'consent' => [], 'consented_at' => now()]);
    $request = openRequest($user, 'erasure');

    $request = requests()->fulfilErasure($request, TestUser::make('Staff'));

    $user->refresh();
    expect($erased)->toBe([$user->id])
        ->and($user->name)->toBe('Deleted User '.$user->id)
        ->and($user->email)->toBe('deleted-user-'.$user->id.'@anonymized.invalid')
        ->and($user->email)->not->toBe($originalEmail)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->not->toBe('x')
        ->and(UserCookieConsent::count())->toBe(0)
        ->and($request->status)->toBe(PrivacyRequestStatus::FULFILLED)
        ->and($request->admin_notes)->toContain('anonymized via DSAR erasure action')
        ->and(auditsOf($request))->toBe(['erasure_fulfilled']);
});

it('leaves the person and the request untouched if erasure fails part way', function () {
    app(PrivacyContributorRegistry::class)->register(new class implements PrivacyContributor
    {
        public function key(): string
        {
            return 'broken';
        }

        public function export(Model $user): array
        {
            return [];
        }

        public function erase(Model $user): void
        {
            throw new RuntimeException('cannot erase');
        }
    });
    $user = TestUser::make('Requester');
    UserCookieConsent::create(['user_id' => $user->id, 'consent' => [], 'consented_at' => now()]);
    $request = openRequest($user, 'erasure');
    $email = $user->email;

    expect(fn () => requests()->fulfilErasure($request))->toThrow(RuntimeException::class);

    expect($user->refresh()->email)->toBe($email)
        ->and(UserCookieConsent::count())->toBe(1)
        ->and($request->refresh()->status)->toBe(PrivacyRequestStatus::RECEIVED)
        ->and(auditsOf($request))->toBe([]);
});

it('skips configured columns the user table does not have, and can blank others', function () {
    config(['filagora-compliance.anonymize.attributes' => ['name' => 'Gone {id}', 'username' => 'deleted_{id}', 'avatar_path' => null]]);
    $user = TestUser::make('Requester');

    requests()->fulfilErasure(openRequest($user, 'erasure'));

    expect($user->refresh()->name)->toBe('Gone '.$user->id)->and($user->email)->not->toContain('anonymized');
});

it('uses the host\'s own anonymiser when it binds one', function () {
    app()->bind(UserAnonymizer::class, fn () => new class implements UserAnonymizer
    {
        public function anonymize(Model $user): void
        {
            $user->forceFill(['name' => 'Gone'])->save();
        }
    });
    app()->forgetInstance(PersonalDataErasure::class);
    app()->forgetInstance(PrivacyRequests::class);
    $user = TestUser::make('Requester');

    requests()->fulfilErasure(openRequest($user, 'erasure'));

    expect($user->refresh()->name)->toBe('Gone')->and($user->email)->not->toContain('anonymized');
});

// ------------------------------------------------------------------ mail

it('mails the requester and the DSAR address when a request is submitted', function () {
    app(SettingsRegistry::class)->set('dsar_request_email', 'privacy@example.test');
    $user = TestUser::make('Requester');

    requests()->submit($user, 'export', 'Please');

    Notification::assertSentTo($user, PrivacyRequestSubmittedNotification::class);
    Notification::assertSentOnDemand(PrivacyRequestSubmittedStaffNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'privacy@example.test');
});

it('mails no one at the DSAR address when none, or a bad one, is set', function () {
    requests()->submit(TestUser::make('One'), 'export', 'Please');
    app(SettingsRegistry::class)->set('dsar_request_email', 'not an address');
    requests()->submit(TestUser::make('Two'), 'export', 'Please');

    Notification::assertNothingSentTo(new AnonymousNotifiable);
    Notification::assertSentOnDemandTimes(PrivacyRequestSubmittedStaffNotification::class, 0);
});

it('mails the requester about a status change and a reply', function () {
    $user = TestUser::make('Requester');
    $request = openRequest($user);

    requests()->changeStatus($request, PrivacyRequestStatus::IN_REVIEW);
    requests()->reply($request, 'Hello');

    Notification::assertSentTo($user, PrivacyRequestStatusNotification::class);
    Notification::assertSentTo($user, PrivacyRequestReplyNotification::class, fn ($n) => str_contains(implode(' ', $n->toMail($user)->introLines), 'Hello'));
});

it('sends no mail at all when switched off', function () {
    config(['filagora-compliance.mail.enabled' => false]);
    $user = TestUser::make('Requester');

    $request = requests()->submit($user, 'export', 'Please');
    requests()->changeStatus($request, PrivacyRequestStatus::IN_REVIEW);
    requests()->reply($request, 'Hello');

    Notification::assertNothingSent();
});
