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

    'stripe' => [
        // Money is routed per event/product to one of three Stripe accounts.
        // 'cafeteria' keeps the legacy STRIPE_* envs so existing deploys keep working.
        'accounts' => [
            'cafeteria' => [
                'key' => env('STRIPE_CAFETERIA_KEY', env('STRIPE_KEY')),
                'secret' => env('STRIPE_CAFETERIA_SECRET', env('STRIPE_SECRET')),
                'webhook_secret' => env('STRIPE_CAFETERIA_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),
            ],
            // Falls back to the dashboard-named vars ("Rifa entre amigos" /
            // "Taquilla Virtual") so production can use either naming.
            'rifa' => [
                'key' => env('STRIPE_RIFA_KEY', env('STRIPE_KEY_RIFA_ENTRE_AMIGOS')),
                'secret' => env('STRIPE_RIFA_SECRET', env('STRIPE_SECRET_RIFA_ENTRE_AMIGOS')),
                'webhook_secret' => env('STRIPE_RIFA_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET_RIFA_ENTRE_AMIGOS')),
            ],
            'eventos' => [
                'key' => env('STRIPE_EVENTOS_KEY', env('STRIPE_KEY_TAQUILLA_VIRTUAL')),
                'secret' => env('STRIPE_EVENTOS_SECRET', env('STRIPE_SECRET_TAQUILLA_VIRTUAL')),
                'webhook_secret' => env('STRIPE_EVENTOS_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET_TAQUILLA_VIRTUAL')),
            ],
        ],
        'default_account' => env('STRIPE_DEFAULT_ACCOUNT', 'cafeteria'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
