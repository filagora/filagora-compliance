<?php

declare(strict_types=1);

return [
    'request_id' => 'Request ID: :id',
    'type' => 'Type: :type',

    'submitted_user' => [
        'subject' => 'We received your privacy request',
        'intro' => 'We have received your privacy request and will review it.',
        'action' => 'View your requests',
    ],

    'submitted_staff' => [
        'subject' => 'New privacy request',
        'intro' => 'A new privacy request needs review.',
        'user' => 'From: :user',
        'action' => 'Open the request',
    ],

    'status' => [
        'subject' => 'Your privacy request was updated',
        'intro' => 'The status of your privacy request has changed.',
        'from' => 'From: :status',
        'to' => 'To: :status',
        'action' => 'View your requests',
        'download_action' => 'Download your data',
        'download_expires' => 'This link expires in :minutes minutes. You can request a new export if it has expired.',
    ],

    'reply' => [
        'subject' => 'A reply to your privacy request',
        'intro' => 'Our team has replied to your privacy request.',
        'message' => 'Message: :message',
        'action' => 'View your requests',
    ],

    'prune_failed' => [
        'subject' => 'A privacy retention rule failed',
        'intro' => 'The retention rule ":rule" failed during the privacy prune.',
    ],
];
