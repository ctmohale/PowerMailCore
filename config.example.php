<?php

return [
    'app' => [
        'name' => 'PowerMail Core',
        'url' => 'https://mailcore.yourdomain.co.za',
        'debug' => false,
        'key' => 'change-this-to-a-long-random-secret-key',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'beestac1_powermail',
        'user' => 'beestac1_powermail',
        'password' => 'your_mysql_password',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'name' => 'PowerMail Admin',
        'email' => 'admin@yourdomain.co.za',
        'password' => 'change-this-password',
    ],
];
