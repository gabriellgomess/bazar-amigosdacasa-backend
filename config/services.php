<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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
    | Trackmob — espelha como lead cada comprador cadastrado no bazar.
    | Enquanto TRACKMOB_ENABLED estiver desligado (ou faltar credencial), a
    | integração fica inerte e o cadastro no bazar segue normalmente.
    */
    'trackmob' => [
        'enabled' => env('TRACKMOB_ENABLED', false),
        'base_url' => env('TRACKMOB_BASE_URL', 'https://api.trackmob.com.br'),
        'client_id' => env('TRACKMOB_CLIENT_ID'),
        'client_secret' => env('TRACKMOB_CLIENT_SECRET'),
        // Campanha "Bazar Cashback".
        'campaign_id' => env('TRACKMOB_CAMPAIGN_ID', '2a05d85b-4f49-46b9-ad83-9ecb7a86b66d'),
        // Timeout curto de propósito: o cadastro não pode ficar pendurado
        // esperando a Trackmob responder.
        'timeout' => env('TRACKMOB_TIMEOUT', 6),
    ],

];
