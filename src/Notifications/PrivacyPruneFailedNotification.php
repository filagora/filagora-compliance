<?php

declare(strict_types=1);

namespace Filagora\Compliance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the DSAR address that a retention rule failed during the prune.
 */
final class PrivacyPruneFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $rule, private readonly string $error) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('filagora-compliance::mail.prune_failed.subject'))
            ->line(__('filagora-compliance::mail.prune_failed.intro', ['rule' => $this->rule]))
            ->line($this->error);
    }
}
