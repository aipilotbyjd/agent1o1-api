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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'gmail' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_sheets' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_docs' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_tasks' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_contacts' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_forms' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_slides' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_youtube' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_analytics' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'google_search_console' => [
        'client_id' => env('GOOGLE_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONNECTORS_CLIENT_SECRET'),
    ],

    'notion' => [
        'client_id' => env('NOTION_CLIENT_ID'),
        'client_secret' => env('NOTION_CLIENT_SECRET'),
    ],

    'linear' => [
        'client_id' => env('LINEAR_CLIENT_ID'),
        'client_secret' => env('LINEAR_CLIENT_SECRET'),
    ],

    'jira' => [
        'client_id' => env('JIRA_CLIENT_ID'),
        'client_secret' => env('JIRA_CLIENT_SECRET'),
    ],

    'docusign' => [
        'client_id' => env('DOCUSIGN_CLIENT_ID'),
        'client_secret' => env('DOCUSIGN_CLIENT_SECRET'),
        // Where DocuSign signs people in: account.docusign.com, or account-d.docusign.com for its developer sandbox.
        'auth_host' => env('DOCUSIGN_AUTH_HOST', 'account.docusign.com'),
    ],

    'zoom' => [
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
    ],

    // Microsoft Teams signs in with the same Microsoft app as Outlook.
    'microsoft_teams' => [
        'client_id' => env('MICROSOFT_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CONNECTORS_CLIENT_SECRET'),
    ],

    'outlook' => [
        'client_id' => env('MICROSOFT_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CONNECTORS_CLIENT_SECRET'),
    ],

];
