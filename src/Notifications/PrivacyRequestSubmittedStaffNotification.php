<?php

declare(strict_types=1);

namespace Filagora\Compliance\Notifications;

use Filagora\Compliance\Support\MailLinks;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the DSAR address a request came in.
 */
final class PrivacyRequestSubmittedStaffNotification extends PrivacyMail
{
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('filagora-compliance::mail.submitted_staff.subject'))
            ->line(__('filagora-compliance::mail.submitted_staff.intro'))
            ->line(__('filagora-compliance::mail.request_id', ['id' => $this->request->id]))
            ->line(__('filagora-compliance::mail.type', ['type' => $this->typeLabel()]))
            ->line(__('filagora-compliance::mail.submitted_staff.user', ['user' => (string) ($this->request->user?->getAttribute('email') ?? 'unknown')]));

        $link = MailLinks::staffRequest($this->request);

        return $link !== null ? $mail->action(__('filagora-compliance::mail.submitted_staff.action'), $link) : $mail;
    }
}
