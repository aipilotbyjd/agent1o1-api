<?php

namespace App\Models\Connectors;

use App\Enums\Connectors\ConnectorAuthType;
use App\Enums\Connectors\ConnectorCategory;
use App\Models\Concerns\TracksAdminEdits;
use Database\Factories\Connectors\ConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The integration catalog (Slack, GitHub, Gmail, ...): one row per
 * `NodeContract::category()` that needs a credential. `fields` is the
 * form schema a manual (`api_key`/`bearer_token`/`basic_auth`) credential's
 * `data` must satisfy; `oauth` carries the authorize/token URLs and default
 * scopes for `auth_type = oauth2` connectors. See docs/PLAN.md Phase 6.
 */
#[Fillable(['key', 'name', 'description', 'icon', 'color', 'category', 'is_featured', 'auth_type', 'fields', 'oauth', 'is_active', 'sort_order', 'admin_edited_fields'])]
class Connector extends Model
{
    /** @use HasFactory<ConnectorFactory> */
    use HasFactory, HasUuids, TracksAdminEdits;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
        'category' => 'other',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auth_type' => ConnectorAuthType::class,
            'category' => ConnectorCategory::class,
            'is_featured' => 'boolean',
            'fields' => 'array',
            'oauth' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isOAuth(): bool
    {
        return $this->auth_type === ConnectorAuthType::OAuth2;
    }

    /**
     * The labels of the fields its form marks required that `$data` leaves
     * empty, so a connection can't be saved half-filled (a Twilio account with
     * no SID, a mail server with no host).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function missingFields(array $data): array
    {
        return collect((array) $this->fields)
            ->filter(fn (mixed $field): bool => is_array($field) && ($field['required'] ?? false) === true && isset($field['name']))
            ->filter(fn (array $field): bool => ! is_scalar($data[$field['name']] ?? null) || trim((string) $data[$field['name']]) === '')
            ->map(fn (array $field): string => (string) ($field['label'] ?? $field['name']))
            ->values()
            ->all();
    }

    /**
     * Whether this server can connect it at all: an OAuth connector needs its
     * client id and secret in `config/services.php` under its own key; the
     * manual kinds need nothing server-side.
     */
    public function isConfigured(): bool
    {
        if (! $this->isOAuth()) {
            return true;
        }

        return filled(config("services.{$this->key}.client_id"))
            && filled(config("services.{$this->key}.client_secret"));
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ConnectorCredential::class);
    }
}
