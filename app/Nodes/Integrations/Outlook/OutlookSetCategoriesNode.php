<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class OutlookSetCategoriesNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_set_categories';
    }

    public function name(): string
    {
        return 'Outlook: Set Categories';
    }

    public function description(): string
    {
        return 'Adds categories to a message, removes some, or replaces them all.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'categories'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'categories' => Field::list('Categories', 'One category name per row, e.g. Red category.', 'Blue category'),
                'mode' => Field::select('What to do', ['add' => 'Add to the ones it has', 'remove' => 'Remove from the ones it has', 'replace' => 'Replace them all'], null, 'add'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = '/me/messages/'.$this->pathId($config, 'message_id', 'The message');
        $given = $config['categories'] ?? [];
        $given = array_values(array_unique(array_filter(array_map(fn (mixed $category): string => trim((string) $category), is_array($given) ? $given : explode(',', (string) $given)))));
        $mode = (string) ($config['mode'] ?? 'add');

        if ($given === [] && $mode !== 'replace') {
            throw new InvalidArgumentException('Give at least one category.');
        }

        $current = $mode === 'replace' ? [] : array_values(array_map('strval', (array) ($this->get($run, $path, $config, ['$select' => 'categories'])['categories'] ?? [])));
        $categories = match ($mode) {
            'remove' => array_values(array_diff($current, $given)),
            'replace' => $given,
            default => array_values(array_unique([...$current, ...$given])),
        };

        $this->patch($run, $path, $config, ['categories' => $categories]);

        return ['message_id' => trim((string) $config['message_id']), 'categories' => $categories];
    }
}
