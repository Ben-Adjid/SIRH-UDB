<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
