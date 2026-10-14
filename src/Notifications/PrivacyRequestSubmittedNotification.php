<?php

declare(strict_types=1);

namespace Filagora\Compliance\Notifications;

use Filagora\Compliance\Support\MailLinks;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the requester their request was received.
 */
final class PrivacyRequestSubmittedNotification extends PrivacyMail
{
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('filagora-compliance::mail.submitted_user.subject'))
            ->line(__('filagora-compliance::mail.submitted_user.intro'))
            ->line(__('filagora-compliance::mail.request_id', ['id' => $this->request->id]))
            ->line(__('filagora-compliance::mail.type', ['type' => $this->typeLabel()]));

        $link = MailLinks::requesterPage();

        return $link !== null ? $mail->action(__('filagora-compliance::mail.submitted_user.action'), $link) : $mail;
    }
}
