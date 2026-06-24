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

    'biometric_webhook' => [
        // Shared secret the biometric/SharePoint Power Automate flow must send
        // in the X-Webhook-Secret header. Keep it in .env only.
        'secret' => env('BIOMETRIC_WEBHOOK_SECRET'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/admin/auth/microsoft/callback'),
        // Use a specific Entra tenant ID, or "common" to allow any org account.
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
    ],

    'sharepoint' => [
        // Mirror biometric punches into the SharePoint "Timekeeping" list via
        // Microsoft Graph (app-only). Off unless explicitly enabled, so the
        // scanner works with the local attendance_logs table alone by default.
        'timekeeping_enabled' => env('SHAREPOINT_TIMEKEEPING_ENABLED', false),

        // App-only (client credentials) registration. These can be the same
        // credentials the DF Portal uses — the granted Graph application
        // permissions belong to the registration, not to a single app.
        'client_id' => env('SHAREPOINT_CLIENT_ID'),
        'client_secret' => env('SHAREPOINT_CLIENT_SECRET'),
        'tenant_id' => env('SHAREPOINT_TENANT_ID'),
        'site_id' => env('SHAREPOINT_SITE_ID'),

        // Display name of the target list and the internal name of its email
        // column (DF Portal's "Email" column is internally named "Class").
        'list_name' => env('SHAREPOINT_TIMEKEEPING_LIST', 'Timekeeping'),
        'email_field' => env('SHAREPOINT_TIMEKEEPING_EMAIL_FIELD', 'Class'),
    ],

    'gif' => [
        // Which GIF search provider to use: "giphy" or "tenor".
        'provider' => env('GIF_PROVIDER', 'giphy'),
        'giphy_key' => env('GIPHY_API_KEY'),
        'tenor_key' => env('TENOR_API_KEY'),
        'rating' => env('GIF_RATING', 'pg'),
    ],

    'chrome' => [
        // Absolute path to a Chrome/Chromium binary for Browsershot (PDF
        // rendering). Leave null to let Browsershot auto-detect. Set this when
        // Puppeteer-managed Chrome isn't auto-resolved, e.g. on Windows/Herd.
        'path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
    ],

];
