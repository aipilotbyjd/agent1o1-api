<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordRespondToInteractionNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_respond_to_interaction';
    }

    public function name(): string
    {
        return 'Discord: Respond to Slash Command or Button';
    }

    public function description(): string
    {
        return 'Answers a slash command or button press, within 15 minutes of it: fills in the "thinking…" reply, or sends another message (which can be private to the person).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['interaction_token'],
            'properties' => [
                ...$this->credentialFields(),
                'interaction_token' => Field::text('Interaction token', 'The interaction_token from the "Slash command" or "Button clicked" trigger.'),
                'mode' => Field::select('Reply by', ['edit_original' => 'Filling in the first reply', 'new_message' => 'Sending another message'], 'The first reply is the "thinking…" message the bot showed. For a button, it updates the message the button is on.', 'edit_original'),
                'ephemeral' => Field::boolean('Only the person sees it', 'Only for "Sending another message".'),
                ...$this->messageFields(reply: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $application = $this->applicationId($run, $config);
        $token = $this->requiredString($config, 'interaction_token', 'The interaction token');
        $body = $this->messageBody($config, allowEmpty: true);
        unset($body['message_reference']);

        if (($config['mode'] ?? 'edit_original') === 'new_message') {
            if (filter_var($config['ephemeral'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $body['flags'] = ($body['flags'] ?? 0) | 64;
            }

            $message = (array) $this->discord($run, $config, 'POST', "/webhooks/{$application}/{$token}", $body);
        } else {
            unset($body['flags'], $body['tts']);
            $message = (array) $this->discord($run, $config, 'PATCH', "/webhooks/{$application}/{$token}/messages/@original", $body);
        }

        return $this->shapeMessage($message);
    }
}
