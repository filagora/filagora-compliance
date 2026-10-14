<?php

declare(strict_types=1);

namespace Filagora\Compliance\Notifications;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Support\MailLinks;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the requester their request changed status; when an export is ready it carries a signed,
 * expiring download link (if the host configured a route for it).
 */
final class PrivacyRequestStatusNotification extends PrivacyMail
{
    public function __construct(
        PrivacyRequest $request,
        private readonly PrivacyRequestStatus $from,
        private readonly PrivacyRequestStatus $to,
    ) {
        parent::__construct($request);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('filagora-compliance::mail.status.subject'))
            ->line(__('filagora-compliance::mail.status.intro'))
            ->line(__('filagora-compliance::mail.request_id', ['id' => $this->request->id]))
            ->line(__('filagora-compliance::mail.type', ['type' => $this->typeLabel()]))
            ->line(__('filagora-compliance::mail.status.from', ['status' => $this->from->label()]))
            ->line(__('filagora-compliance::mail.status.to', ['status' => $this->to->label()]));

        if ($this->to === PrivacyRequestStatus::FULFILLED && $this->request->type === PrivacyRequestType::EXPORT && $this->request->export_path) {
            $minutes = max(1, (int) config('filagora-compliance.exports.download_link_minutes', 10080));
            $download = MailLinks::exportDownload($this->request, $minutes);

            if ($download !== null) {
                return $mail
                    ->action(__('filagora-compliance::mail.status.download_action'), $download)
                    ->line(__('filagora-compliance::mail.status.download_expires', ['minutes' => $minutes]));
            }
        }

        $link = MailLinks::requesterPage();

        return $link !== null ? $mail->action(__('filagora-compliance::mail.status.action'), $link) : $mail;
    }
}
