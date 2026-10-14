<?php

declare(strict_types=1);

use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\PrivacyOperationAudit;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Models\UserCookieConsent;
use Filagora\Compliance\Models\UserPrivacyPolicyAcceptance;

return [

    /*
    |--------------------------------------------------------------------------
    | Swappable models
    |--------------------------------------------------------------------------
    |
    | The classes this package uses for its tables. Point a key at your own subclass to
    | replace one (here, or under `filagora.models.<key>`, which wins). Subclass the package
    | model; the package always asks for the class through its Models helper.
    |
    */
    'models' => [
        'cookie_consent_group' => CookieConsentGroup::class,
        'cookie_consent_cookie' => CookieConsentCookie::class,
        'cookie_policy_version' => CookiePolicyVersion::class,
        'user_cookie_consent' => UserCookieConsent::class,
        'privacy_policy_version' => PrivacyPolicyVersion::class,
        'user_privacy_policy_acceptance' => UserPrivacyPolicyAcceptance::class,
        'privacy_request' => PrivacyRequest::class,
        'privacy_operation_audit' => PrivacyOperationAudit::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate abilities
    |--------------------------------------------------------------------------
    |
    | Maps each ability that guards a compliance screen or action to the key that allows it:
    | seeing privacy requests, handling them, erasing a person, managing the cookie policy and
    | consent groups, managing the privacy policy versions, and reading the operation audit. Point an ability at your own entitlement key to use your own keys.
    |
    */
    'gates' => [
        'filagora.compliance.privacy_requests.view' => 'filagora.compliance.privacy_requests.view',
        'filagora.compliance.privacy_requests' => 'filagora.compliance.privacy_requests',
        'filagora.compliance.erase' => 'filagora.compliance.erase',
        'filagora.compliance.cookie_policies' => 'filagora.compliance.cookie_policies',
        'filagora.compliance.privacy_policies' => 'filagora.compliance.privacy_policies',
        'filagora.compliance.audit' => 'filagora.compliance.audit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | Whether the default listeners email the requester and the DSAR address (setting
    | `dsar_request_email`) about privacy request events. Turn it off to send your own.
    |
    */
    'mail' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Links in the mail
    |--------------------------------------------------------------------------
    |
    | Named routes of your application. Any left empty (or not defined) is simply left out of
    | the mail. `requester` is the page where a person sees their requests; `staff_request`
    | opens a request for staff (it receives `privacyRequest`); `export_download` is a signed
    | route that serves an export file (it receives `privacyRequest`).
    |
    */
    'routes' => [
        'requester' => null,
        'staff_request' => null,
        'export_download' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    |
    | Where export files are written, and which of the user's own columns head the export. Other
    | sections come from whatever registered a PrivacyContributor.
    |
    */
    'exports' => [
        'disk' => env('FILAGORA_COMPLIANCE_EXPORTS_DISK', 'local'),
        'directory' => 'exports/privacy',
        'download_link_minutes' => (int) env('FILAGORA_COMPLIANCE_EXPORT_LINK_MINUTES', 10080),
        'user_attributes' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Erasure
    |--------------------------------------------------------------------------
    |
    | What the default anonymiser puts in the user's columns (`{id}` is the user's id; columns the
    | table does not have are skipped). Bind your own Filagora\Compliance\Contracts\UserAnonymizer
    | to cover columns of your own.
    |
    */
    'anonymize' => [
        'attributes' => [
            'name' => 'Deleted User {id}',
            'email' => 'deleted-user-{id}@anonymized.invalid',
            'email_verified_at' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long this package keeps privacy requests, in days after they were settled (zero keeps
    | them for ever). Requests on legal hold are never deleted. Export files are deleted
    | `delete_export_after_days` after the request was fulfilled. Other packages register their
    | own rules with core's RetentionRuleRegistry; all run in `filagora:compliance:prune`, which is
    | scheduled daily unless `schedule.enabled` is false.
    |
    */
    'retention' => [
        'privacy_requests' => [
            'received_stale_days' => 365,
            'in_review_stale_days' => 730,
            'fulfilled_days' => 1095,
            'rejected_days' => 1095,
            'delete_export_after_days' => 30,
        ],
    ],

    'schedule' => [
        'enabled' => env('FILAGORA_COMPLIANCE_SCHEDULE_ENABLED', true),
        'daily_at' => env('FILAGORA_COMPLIANCE_SCHEDULE_AT', '02:15'),
    ],

    /*
    |--------------------------------------------------------------------------
    | KPIs
    |--------------------------------------------------------------------------
    |
    | The dashboard widget: the deadline for fulfilling a request, how long a new request may
    | wait for a first review, and how far back fulfilled requests are counted.
    |
    */
    'kpis' => [
        'sla_days' => 30,
        'overdue_review_days' => 7,
        'sla_window_days' => 90,
    ],

];
