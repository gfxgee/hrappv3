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

    'teams' => [
        // Power Automate "When an HTTP request is received" endpoint that posts
        // to a Teams channel. Carries a signature token — keep it in .env only.
        'flow_url' => env('TEAMS_FLOW_URL'),
        // Who to @mention for approval when the employee's department has no
        // team leader set.
        'default_approver' => env('TEAMS_DEFAULT_APPROVER', 'gee@digitalfeet.com'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/admin/auth/microsoft/callback'),
        // Use a specific Entra tenant ID, or "common" to allow any org account.
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    'gif' => [
        // Which GIF search provider to use: "giphy" or "tenor".
        'provider' => env('GIF_PROVIDER', 'giphy'),
        'giphy_key' => env('GIPHY_API_KEY'),
        'tenor_key' => env('TENOR_API_KEY'),
        'rating' => env('GIF_RATING', 'pg'),
    ],

];
