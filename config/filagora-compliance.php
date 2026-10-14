<?php

declare(strict_types=1);

return [

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
