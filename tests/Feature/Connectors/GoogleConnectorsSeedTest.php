<?php

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use App\Models\Nodes\NodeCategory;
use App\Services\Workflows\NodeRegistry;

it('seeds a connector and a node category for every google node family', function () {
    $this->syncSetup('connectors', 'app-node-categories');

    $registry = app(NodeRegistry::class);

    $categories = collect(array_keys($registry->connectors()))
        ->map(fn (string $type): string => $registry->resolve($type)->category())
        ->filter(fn (string $category): bool => $category === 'gmail' || str_starts_with($category, 'google_'))
        ->unique()
        ->sort()
        ->values();

    expect($categories->all())->toBe([
        'gmail', 'google_analytics', 'google_calendar', 'google_contacts', 'google_docs', 'google_drive',
        'google_forms', 'google_search_console', 'google_sheets', 'google_slides', 'google_tasks', 'google_youtube',
    ]);

    foreach ($categories as $key) {
        $connector = Connector::query()->where('key', $key)->first();

        expect($connector)->not->toBeNull("No connector seeded for {$key}")
            ->and($connector->auth_type)->toBe(ConnectorAuthType::OAuth2)
            ->and($connector->oauth['authorize_url'])->toBe('https://accounts.google.com/o/oauth2/v2/auth')
            ->and($connector->oauth['authorize_params'])->toMatchArray(['access_type' => 'offline', 'prompt' => 'consent'])
            ->and($connector->oauth['scopes'])->not->toBeEmpty()
            ->and(config("services.{$key}.client_id"))->toBe(config('services.gmail.client_id'));

        expect(NodeCategory::query()->where('slug', $key)->where('kind', 'app')->exists())->toBeTrue("No node category for {$key}");
    }
});

it('asks each new google app only for the scopes it needs', function () {
    $this->syncSetup('connectors');

    $scopes = fn (string $key): array => Connector::query()->where('key', $key)->firstOrFail()->oauth['scopes'];

    expect($scopes('google_tasks'))->toBe(['https://www.googleapis.com/auth/tasks'])
        ->and($scopes('google_contacts'))->toBe(['https://www.googleapis.com/auth/contacts'])
        ->and($scopes('google_youtube'))->toBe(['https://www.googleapis.com/auth/youtube.force-ssl', 'https://www.googleapis.com/auth/youtube.upload'])
        ->and($scopes('google_analytics'))->toBe(['https://www.googleapis.com/auth/analytics.readonly'])
        ->and($scopes('google_search_console'))->toBe(['https://www.googleapis.com/auth/webmasters.readonly'])
        ->and($scopes('google_forms'))->toContain('https://www.googleapis.com/auth/forms.responses.readonly')
        ->and($scopes('google_slides'))->toContain('https://www.googleapis.com/auth/presentations');
});

it('asks Sheets and Docs for Drive file metadata, which their instant triggers need', function () {
    $this->syncSetup('connectors');

    foreach (['google_sheets', 'google_docs'] as $key) {
        expect(Connector::query()->where('key', $key)->firstOrFail()->oauth['scopes'])->toContain('https://www.googleapis.com/auth/drive.metadata.readonly');
    }
});

it('offers every Google trigger in the Triggers & Events catalog', function () {
    $triggers = collect(app(NodeRegistry::class)->triggerCatalog())
        ->filter(fn (array $entry): bool => $entry['category'] === 'gmail' || str_starts_with($entry['category'], 'google_'))
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($triggers)->toBe([
        'Gmail: New email',
        'Gmail: New email with attachment',
        'Google Calendar: Event cancelled',
        'Google Calendar: Event starting soon',
        'Google Calendar: Event updated',
        'Google Calendar: New event',
        'Google Contacts: Contact added or updated',
        'Google Contacts: New contact',
        'Google Docs: Document updated',
        'Google Drive: File trashed',
        'Google Drive: File updated',
        'Google Drive: New file',
        'Google Forms: New response',
        'Google Sheets: New row',
        'Google Sheets: Row added or updated',
        'Google Sheets: Row deleted',
        'Google Tasks: New task',
        'Google Tasks: Task completed',
        'Google Tasks: Task due soon',
        'YouTube: New comment',
        'YouTube: New subscriber',
        'YouTube: New video',
    ]);
});

it('asks Slack and GitHub for every permission their nodes and triggers use', function () {
    $this->syncSetup('connectors');
    $scopes = fn (string $key): array => Connector::query()->where('key', $key)->firstOrFail()->oauth['scopes'];

    expect($scopes('slack'))->toContain('chat:write', 'im:write', 'reactions:write', 'pins:write', 'files:write', 'users:read.email', 'channels:manage', 'channels:join', 'app_mentions:read')
        ->and($scopes('github'))->toContain('repo', 'workflow', 'gist', 'read:org', 'notifications');
});
