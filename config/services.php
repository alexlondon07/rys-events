<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Drive
    |--------------------------------------------------------------------------
    |
    | `credentials` es la ruta al JSON de la cuenta de servicio (recomendado).
    | `api_key` sirve como alternativa para carpetas públicas ("cualquiera con
    | el enlace"). Con la cuenta de servicio, el cliente comparte la carpeta
    | raíz de eventos con el correo de esa cuenta como lector, una sola vez.
    |
    */

    'google' => [
        'drive' => [
            'credentials' => env('GOOGLE_DRIVE_CREDENTIALS'),
            'api_key' => env('GOOGLE_DRIVE_API_KEY'),
        ],
    ],

];
