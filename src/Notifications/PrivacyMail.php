<?php

declare(strict_types=1);

namespace Filagora\Compliance\Notifications;

use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base of the privacy request mail: queued, mail only, and one description of the request.
 */
abstract class PrivacyMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected readonly PrivacyRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function typeLabel(): string
    {
        return $this->request->type->label();
    }
}
