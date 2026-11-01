<?php

namespace App\Nodes\Integrations\Postmark;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PostmarkListSpamComplaintsNode extends AbstractPostmarkNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'postmark_list_spam_complaints';
    }

    public function name(): string
    {
        return 'Postmark: List Spam Complaints';
    }

    public function description(): string
    {
        return 'Lists the people who marked your email as spam, newest first. As a trigger it starts the flow for each new complaint.';
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
                'since' => Field::advanced(Field::datetime('Complained after')),
                'stream' => $this->streamField(),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $bounces = $this->listAll($run, $config, '/bounces', 'Bounces', [
            'type' => 'SpamComplaint',
            'fromdate' => $this->moment($config, 'since'),
            'messagestream' => filled($config['stream'] ?? null) ? trim((string) $config['stream']) : null,
        ], $this->limit($config, 50));

        return ['complaints' => array_map($this->shapeBounce(...), $bounces), 'count' => count($bounces)];
    }

    private const int TRIGGER_WINDOW_MINUTES = 360;

    protected function triggerItemsPath(): ?string
    {
        return 'complaints';
    }

    public function triggerName(): string
    {
        return 'Postmark: Spam complaint';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone marks your email as spam. Instant, with a check every few minutes.';
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
