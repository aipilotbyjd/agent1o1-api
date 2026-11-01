<?php

namespace App\Nodes\Integrations\Postmark;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PostmarkListBouncesNode extends AbstractPostmarkNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'postmark_list_bounces';
    }

    public function name(): string
    {
        return 'Postmark: List Bounces';
    }

    public function description(): string
    {
        return 'Lists the bounces, newest first, by type or time. As a trigger it starts the flow for each new bounce.';
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
                'type' => Field::advanced(Field::select('Type', ['any' => 'Any', 'HardBounce' => 'Hard bounce', 'SoftBounce' => 'Soft bounce', 'Transient' => 'Transient', 'SpamNotification' => 'Spam notification', 'Blocked' => 'Blocked', 'DnsError' => 'DNS error', 'ManuallyDeactivated' => 'Manually deactivated'], null, 'any')),
                'email' => Field::advanced(Field::text('Email address')),
                'since' => Field::advanced(Field::datetime('Bounced after')),
                'stream' => $this->streamField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = (string) ($config['type'] ?? 'any');

        $bounces = $this->listAll($run, $config, '/bounces', 'Bounces', [
            'type' => $type === 'any' ? null : $type,
            'emailFilter' => filled($config['email'] ?? null) ? trim((string) $config['email']) : null,
            'fromdate' => $this->moment($config, 'since'),
            'messagestream' => filled($config['stream'] ?? null) ? trim((string) $config['stream']) : null,
        ], $this->limit($config, 50));

        return ['bounces' => array_map($this->shapeBounce(...), $bounces), 'count' => count($bounces)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'bounces';
    }

    public function triggerName(): string
    {
        return 'Postmark: Email bounced';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an email you sent bounces (pick the type, or any). Instant, with a check every few minutes.';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'since' => now()->subMinutes(self::TRIGGER_WINDOW_MINUTES)->toIso8601String(), 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
