<?php

/**
 * The OAuth app each connector connects accounts through, keyed by connector.
 */
return [

    'github' => [
        'client_id' => env('GITHUB_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('GITHUB_CONNECTORS_CLIENT_SECRET'),
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

    'outlook' => [
        'client_id' => env('MICROSOFT_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CONNECTORS_CLIENT_SECRET'),
    ],

    'microsoft_teams' => [
        'client_id' => env('MICROSOFT_CONNECTORS_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CONNECTORS_CLIENT_SECRET'),
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
        'auth_host' => env('DOCUSIGN_AUTH_HOST', 'account.docusign.com'),
    ],

    'zoom' => [
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
    ],

];
