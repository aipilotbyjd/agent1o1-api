<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Connectors\ConnectorAuthType;
use App\Enums\Connectors\ConnectorCategory;
use App\Enums\Setup\Ownership;
use App\Models\Connectors\Connector;
use App\Services\Setup\AbstractSetupDataset;

/**
 * The integration catalog backing `ConnectorCredential`. `key` matches the
 * `node_categories.slug` seeded by `AppNodeCategoriesDataset` for the same
 * integration, so a node's `category()` doubles as its connector lookup key.
 *
 * Every Google product is its own connector: its own "Connect" button, its
 * own consent screen, its own scope, rather than one shared Google login
 * covering all of them. Matches how Gumloop (and most workflow tools) model
 * Google integrations: users grant only what a given product needs.
 */
class ConnectorsDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'connectors';
    }

    public function label(): string
    {
        return 'Connectors';
    }

    public function ownership(): Ownership
    {
        return Ownership::Shared;
    }

    public function dependencies(): array
    {
        return ['app-node-categories'];
    }

    public function rows(): int
    {
        return Connector::query()->count();
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/connectors.php')];
    }

    public const string GOOGLE_AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const string GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Without these Google issues no refresh token, and the connection dies
     * with its first access token an hour later.
     */
    public const array GOOGLE_AUTHORIZE_PARAMS = ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'];

    public const string MICROSOFT_AUTHORIZE_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';

    public const string MICROSOFT_TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    public function apply(): void
    {
        foreach ($this->connectors() as $connector) {
            $values = [
                'name' => $connector['name'],
                'description' => $connector['description'],
                'icon' => $connector['icon'],
                'color' => $connector['color'],
                'category' => $connector['category'],
                'is_featured' => $connector['is_featured'],
                'auth_type' => $connector['auth_type'],
                'fields' => $connector['fields'],
                'oauth' => $connector['oauth'],
                'sort_order' => $connector['sort_order'],
            ];

            // What an admin changed in the catalog stays as they left it.
            $row = Connector::query()->firstOrNew(['key' => $connector['key']]);
            $row->fill($row->exists ? $row->withoutAdminEdited($values) : $values)->save();
        }
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     icon: string,
     *     color: string,
     *     category: ConnectorCategory,
     *     is_featured: bool,
     *     auth_type: ConnectorAuthType,
     *     fields: array<int, mixed>,
     *     oauth: array{authorize_url: string, token_url: string, scopes: array<int, string>},
     *     sort_order: int,
     * }>
     */
    private function connectors(): array
    {
        return require database_path('setup/connectors.php');
    }

    public function problems(): array
    {
        return $this->check(require database_path('setup/connectors.php'));
    }

    /**
     * @param  list<array<string, mixed>>  $connectors
     * @return list<string>
     */
    public function check(array $connectors): array
    {
        $problems = [];
        $apps = app(AppNodeCategoriesDataset::class)->definedSlugs();

        foreach ($this->duplicates(array_column($connectors, 'key')) as $key) {
            $problems[] = "Connector [{$key}] appears more than once.";
        }

        foreach ($connectors as $connector) {
            $key = $connector['key'] ?? '?';

            foreach ($this->missing($connector, ['key', 'name', 'description', 'icon', 'color']) as $field) {
                $problems[] = "Connector [{$key}] has no {$field}.";
            }

            if (! in_array($key, $apps, true)) {
                $problems[] = "Connector [{$key}] has no app category with the same slug.";
            }

            if ($connector['auth_type'] === ConnectorAuthType::OAuth2) {
                foreach (['authorize_url', 'token_url'] as $field) {
                    if (blank($connector['oauth'][$field] ?? null)) {
                        $problems[] = "OAuth connector [{$key}] has no {$field}.";
                    }
                }
            }

            foreach ($connector['fields'] as $field) {
                if (blank($field['name'] ?? null) || blank($field['type'] ?? null)) {
                    $problems[] = "Connector [{$key}] has a field without a name or type.";
                }
            }
        }

        return $problems;
    }
}
