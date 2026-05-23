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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'bch' => [
        'api_base'       => env('BCH_API_BASE', 'https://gw.apistore.bancochile.cl/banco-chile/sandbox/v1/movimientos-cuenta'),
        'client_id'      => env('BCH_CLIENT_ID'),
        'client_secret'  => env('BCH_CLIENT_SECRET'),
        'cuenta'         => env('BCH_CUENTA'),
        'rut_origen'     => env('BCH_RUT_ORIGEN'),
        'producto_cuenta' => env('BCH_PRODUCTO_CUENTA', 'CTD'),
        'rut_apoderado'  => env('BCH_RUT_APODERADO'),
    ],

    'bsale' => [
        'base_url' => env('BSALE_BASE_URL', 'https://api.bsale.io/v1/'),
        'access_token' => env('BSALE_ACCESS_TOKEN'),
        'token' => env('BSALE_TOKEN'),
    ],

];
