<?php

return [
    'app' => [
        'name' => 'PowerMail Core',
        'url' => 'http://127.0.0.1:8000',
        'debug' => false,
        'key' => 'local-development-secret-key-change-before-production',
    ],
    'database' => [
        'driver' => 'sqlite',
        'path' => __DIR__.'/storage/powermail.sqlite',
    ],
    'admin' => [
        'name' => 'PowerMail Admin',
        'email' => 'admin@powermail.local',
        'password' => 'password',
    ],
];
