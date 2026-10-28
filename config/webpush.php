<?php

return [
    'vapid' => [
        'subject'     => env('VAPID_SUBJECT') ?: 'https://beauty-master.duckdns.org',
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
