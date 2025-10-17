<?php


return [
    'api_key'               => env('LUNCH_FLOW_API_KEY', ''),
    'unique_column_options' => [
        'external-id' => 'External identifier',
    ],
    'api_url'               => envNonEmpty('LUNCH_FLOW_API_URL', 'https://lunchflow.app/api/v1/'),
];
