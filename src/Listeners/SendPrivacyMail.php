<?php

declare(strict_types=1);

namespace Filagora\Compliance\Listeners;

use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Compliance\Notifications\PrivacyRequestReplyNotification;
use Filagora\Compliance\Notifications\PrivacyRequestStatusNotification;
use Filagora\Compliance\Notifications\PrivacyRequestSubmittedNotification;
use Filagora\Compliance\Notifications\PrivacyRequestSubmittedStaffNotification;
use Filagora\Core\Settings\SettingsRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * The default mail for the privacy request events. Silent when `filagora-compliance.mail.enabled`
 * is off; a host that wants different mail turns that off and listens to the events itself.
 */
final class SendPrivacyMail
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function submitted(PrivacyRequestSubmitted $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->notifyRequester($event->request->user, new PrivacyRequestSubmittedNotification($event->request));

        $staff = trim($this->settings->string('dsar_request_email'));

        if (filter_var($staff, FILTER_VALIDATE_EMAIL)) {
            Notification::route('mail', $staff)->notify(new PrivacyRequestSubmittedStaffNotification($event->request));
        }
    }

    public function statusChanged(PrivacyRequestStatusChanged $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->notifyRequester($event->request->user, new PrivacyRequestStatusNotification($event->request, $event->from, $event->to));
    }

    public function replied(PrivacyReplySent $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->notifyRequester($event->request->user, new PrivacyRequestReplyNotification($event->request, $event->message));
    }

    private function enabled(): bool
    {
        return (bool) config('filagora-compliance.mail.enabled', true);
    }

    private function notifyRequester(mixed $user, \Illuminate\Notifications\Notification $notification): void
    {
        if ($user instanceof Model && method_exists($user, 'notify')) {
            $user->notify($notification);
        }
    }
}
