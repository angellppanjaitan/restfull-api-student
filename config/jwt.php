<?php

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'ttl' => (int) env('JWT_TTL', 60),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
];
