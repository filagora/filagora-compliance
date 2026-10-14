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
    | Maps each ability that guards a compliance screen to the entitlement that allows
    | it. Point an ability at your own entitlement key to use your own keys.
    |
    */
    'gates' => [
        'filagora.compliance.privacy_requests' => 'filagora.compliance.privacy_requests',
        'filagora.compliance.policies' => 'filagora.compliance.policies',
        'filagora.compliance.audit' => 'filagora.compliance.audit',
    ],

];
