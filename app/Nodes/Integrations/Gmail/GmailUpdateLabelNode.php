<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GmailUpdateLabelNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_update_label';
    }

    public function name(): string
    {
        return 'Gmail: Rename or Edit Label';
    }

    public function description(): string
    {
        return 'Renames a label or changes where it shows. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['label_id'],
            'properties' => [
                ...$this->credentialFields(),
                'label_id' => Field::dynamic('Label', 'gmail.labels', null, 'Clients'),
                'name' => Field::text('New name', 'Use / for a nested label, e.g. Clients/Acme.'),
                'label_visibility' => Field::advanced(Field::select('In the label list', ['unchanged' => 'Leave as it is', 'labelShow' => 'Show', 'labelShowIfUnread' => 'Show if unread', 'labelHide' => 'Hide'], null, 'unchanged')),
                'message_visibility' => Field::advanced(Field::select('On messages', ['unchanged' => 'Leave as it is', 'show' => 'Show', 'hide' => 'Hide'], null, 'unchanged')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $labelVisibility = (string) ($config['label_visibility'] ?? 'unchanged');
        $messageVisibility = (string) ($config['message_visibility'] ?? 'unchanged');
        $body = array_filter([
            'name' => filled($config['name'] ?? null) ? trim((string) $config['name']) : null,
            'labelListVisibility' => in_array($labelVisibility, ['labelShow', 'labelShowIfUnread', 'labelHide'], true) ? $labelVisibility : null,
            'messageListVisibility' => in_array($messageVisibility, ['show', 'hide'], true) ? $messageVisibility : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        $label = $this->patch($run, '/users/me/labels/'.$this->pathId($config, 'label_id', 'The label'), $config, $body);

        return ['id' => $label['id'] ?? $config['label_id'], 'name' => $label['name'] ?? null, 'type' => $label['type'] ?? null];
    }
}
