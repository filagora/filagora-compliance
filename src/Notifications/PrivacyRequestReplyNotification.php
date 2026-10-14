<?php

declare(strict_types=1);

namespace Filagora\Compliance\Notifications;

use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Support\MailLinks;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sends the requester a staff reply.
 */
final class PrivacyRequestReplyNotification extends PrivacyMail
{
    public function __construct(PrivacyRequest $request, private readonly string $message)
    {
        parent::__construct($request);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('filagora-compliance::mail.reply.subject'))
            ->line(__('filagora-compliance::mail.reply.intro'))
            ->line(__('filagora-compliance::mail.request_id', ['id' => $this->request->id]))
            ->line(__('filagora-compliance::mail.type', ['type' => $this->typeLabel()]))
            ->line(__('filagora-compliance::mail.reply.message', ['message' => $this->message]));

        $link = MailLinks::requesterPage();

        return $link !== null ? $mail->action(__('filagora-compliance::mail.reply.action'), $link) : $mail;
    }
}
