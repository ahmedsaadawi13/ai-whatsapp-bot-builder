<?php
// FILE: /config/app.php

/**
 * Application Configuration
 */

return [
    'name' => 'AI WhatsApp Bot Builder',
    'version' => '1.0.0',
    'timezone' => 'UTC',
    'url' => getenv('APP_URL') ?: 'http://localhost',

    'debug' => getenv('APP_DEBUG') === 'true',

    'pagination' => [
        'per_page' => 20
    ],

    'upload' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'doc', 'docx']
    ],

    'session' => [
        'lifetime' => 120, // minutes
        'cookie_name' => 'ai_whatsapp_session'
    ]
];
