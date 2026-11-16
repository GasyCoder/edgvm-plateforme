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

    /* WhatsApp Business : « log » en local et dans les tests (rien n'est envoyé), « meta » en production. */
    'whatsapp' => [
        'pilote' => env('WHATSAPP_PILOTE', 'log'),
        'jeton' => env('WHATSAPP_JETON'),
        'numero_id' => env('WHATSAPP_NUMERO_ID'),
        'version' => env('WHATSAPP_VERSION', 'v21.0'),
        'langue' => env('WHATSAPP_LANGUE', 'fr'),
        'modele_code' => env('WHATSAPP_MODELE_CODE', 'code_verification'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
