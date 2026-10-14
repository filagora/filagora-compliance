<?php

declare(strict_types=1);

namespace Filagora\Compliance\Support;

use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * The links in the privacy mail. They point at the host's pages, so each is a named route set in
 * `filagora-compliance.routes`; a link with no route configured (or no such route) is left out.
 */
final class MailLinks
{
    /** Where the requester sees their requests. */
    public static function requesterPage(): ?string
    {
        return self::route('requester');
    }

    /** Where staff open a request. Receives the request as the `privacyRequest` parameter. */
    public static function staffRequest(PrivacyRequest $request): ?string
    {
        return self::route('staff_request', ['privacyRequest' => $request->id]);
    }

    /** A signed, expiring link to download an export. */
    public static function exportDownload(PrivacyRequest $request, int $minutes): ?string
    {
        $name = config('filagora-compliance.routes.export_download');

        return is_string($name) && $name !== '' && Route::has($name)
            ? URL::temporarySignedRoute($name, now()->addMinutes($minutes), ['privacyRequest' => $request->id])
            : null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function route(string $key, array $parameters = []): ?string
    {
        $name = config("filagora-compliance.routes.{$key}");

        return is_string($name) && $name !== '' && Route::has($name) ? route($name, $parameters) : null;
    }
}
