<?php

declare(strict_types=1);

return [
    'requests' => [
        'navigation_label' => 'Privacy requests',
        'model_label' => 'privacy request',
        'plural_model_label' => 'privacy requests',
        'table' => [
            'user' => 'User',
            'legal_hold' => 'Legal hold',
            'on_hold' => 'On hold',
            'no' => 'No',
            'hide_rejected' => 'Hide rejected requests',
        ],
        'infolist' => [
            'request' => 'Request',
            'handling' => 'Handling',
            'notes' => 'Notes',
            'export_path' => 'Export file',
            'hold_reason' => 'Hold reason',
            'request_details' => 'What was asked',
            'admin_notes' => 'Staff notes',
        ],
        'actions' => [
            'set_status' => 'Change status',
            'status' => 'Status',
            'hold' => 'Put on legal hold',
            'hold_reason' => 'Reason',
            'release' => 'Release hold',
            'reply' => 'Send reply',
            'message' => 'Message',
            'export' => 'Generate export',
            'export_heading' => 'Generate the data export',
            'export_description' => 'This writes a JSON export of everything registered about this person and marks the request fulfilled.',
            'erase' => 'Erase this person',
            'erase_heading' => 'Erase this person',
            'erase_description' => 'This removes what the installed packages hold about them and makes their account anonymous. It cannot be undone.',
            'reject' => 'Reject',
            'reject_note' => 'Note (optional)',
            'add_note' => 'Add note',
            'note' => 'Note',
            'download' => 'Download export',
            'audits' => 'View operation audit',
        ],
        'notifications' => [
            'status_changed' => 'Status changed',
            'held' => 'Request put on hold',
            'released' => 'Hold released',
            'replied' => 'Reply sent',
            'exported' => 'Export generated',
            'erased' => 'Person erased',
            'rejected' => 'Request rejected',
            'noted' => 'Note added',
            'refused' => 'Not done',
            'missing_file' => 'The export file is no longer there.',
        ],
    ],

    'cookie_groups' => [
        'navigation_label' => 'Cookie groups',
        'model_label' => 'cookie group',
        'plural_model_label' => 'cookie groups',
        'cookies' => 'Cookies',
        'required_help' => 'A required group is always on and cannot be refused.',
    ],

    'cookie_versions' => [
        'navigation_label' => 'Cookie policy versions',
        'model_label' => 'cookie policy version',
        'plural_model_label' => 'cookie policy versions',
    ],

    'privacy_versions' => [
        'navigation_label' => 'Privacy policy versions',
        'model_label' => 'privacy policy version',
        'plural_model_label' => 'privacy policy versions',
    ],

    'audits' => [
        'navigation_label' => 'Privacy operation audit',
        'model_label' => 'operation audit',
        'plural_model_label' => 'operation audits',
        'request_id' => 'Request ID',
        'actor' => 'Done by',
        'person' => 'About',
    ],

    'kpis' => [
        'sla_title' => 'Fulfilled within :days days',
        'sla_none' => 'n/a',
        'sla_description' => ':within of :total fulfilled in time over :window days; :breaches open past the deadline',
        'open_title' => 'Open requests',
        'open_description' => ':count on legal hold',
        'overdue_title' => 'Waiting for review',
        'overdue_description' => 'Received more than :days days ago, not yet picked up',
    ],
];
