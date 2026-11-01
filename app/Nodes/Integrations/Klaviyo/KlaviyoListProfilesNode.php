<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class KlaviyoListProfilesNode extends AbstractKlaviyoNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'klaviyo_list_profiles';
    }

    public function name(): string
    {
        return 'Klaviyo: List Profiles';
    }

    public function description(): string
    {
        return 'Lists profiles, newest first, optionally created since a time. As a trigger it starts the flow for each new profile.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'created_after' => Field::advanced(Field::datetime('Created after')),
                'filter' => Field::advanced(Field::text('Klaviyo filter', 'Your own filter, e.g. equals(properties.plan,"pro"). Combined with the date above.')),
                'limit' => Field::integer('Max results', null, 50, 1, 10000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filters = array_values(array_filter([
            filled($config['created_after'] ?? null) ? 'greater-than(created,'.Carbon::parse((string) $config['created_after'])->utc()->format('Y-m-d\\TH:i:s\\Z').')' : null,
            filled($config['filter'] ?? null) ? trim((string) $config['filter']) : null,
        ]));

        [$profiles] = $this->listAll($run, $config, '/profiles', [
            'filter' => $filters === [] ? null : (count($filters) === 1 ? $filters[0] : 'and('.implode(',', $filters).')'),
            'sort' => '-created',
            'additional-fields[profile]' => 'subscriptions',
        ], $this->limit($config, 50, 10000));

        return ['profiles' => array_map($this->shapeProfile(...), $profiles), 'count' => count($profiles)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'profiles';
    }

    public function triggerName(): string
    {
        return 'Klaviyo: New profile';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a profile is created in Klaviyo. Checks every few minutes.';
    }

    /**
     * Those created in the look-back window.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'created_after' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
