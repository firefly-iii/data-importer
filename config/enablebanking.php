<?php
return [
    'application_id'  => env('ENABLE_BANKING_APP_ID', ''),
    'private_key'     => env('ENABLE_BANKING_PRIVATE_KEY', ''),
    'url'             => env('ENABLE_BANKING_URL', 'https://api.enablebanking.com'),
    'countries'       => require __DIR__ . '/shared/countries.php',
];
