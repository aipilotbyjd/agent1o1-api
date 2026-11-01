<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TypeformCreateWebhookNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_create_webhook';
    }

    public function name(): string
    {
        return 'Typeform: Create Webhook';
    }

    public function description(): string
    {
        return 'Sends each new response of a form to a URL (create or update, by tag). Not needed for triggers: those are set up for you.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['form_id', 'tag', 'url'],
            'properties' => [
                ...$this->credentialFields(),
                'form_id' => $this->formField(),
                'tag' => Field::text('Tag', 'A name for this webhook on the form (letters, digits, - and _).', 'crm-sync'),
                'url' => Field::text('URL', 'Where to send responses. Must be https.', 'https://example.com/hook'),
                'secret' => Field::advanced(Field::text('Secret', 'Typeform signs each request with it (Typeform-Signature).')),
                'enabled' => Field::boolean('Enabled', null, true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $form = $this->formId($config);
        $tag = $this->requiredString($config, 'tag', 'The tag');
        $url = $this->requiredString($config, 'url', 'The URL');

        if (preg_match('/^[\w-]{1,64}$/', $tag) !== 1) {
            throw new InvalidArgumentException('A tag has letters, digits, - and _ only (up to 64).');
        }

        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('Typeform only sends to https:// URLs.');
        }

        $hook = (array) $this->typeform($run, $config, 'PUT', "/forms/{$form}/webhooks/".rawurlencode($tag), array_filter([
            'url' => $url,
            'enabled' => filter_var($config['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'secret' => filled($config['secret'] ?? null) ? (string) $config['secret'] : null,
            'verify_ssl' => true,
            'event_types' => ['form_response' => true],
        ], fn (mixed $value): bool => $value !== null));

        return ['tag' => $hook['tag'] ?? $tag, 'url' => $hook['url'] ?? $url, 'enabled' => $hook['enabled'] ?? null, 'form_id' => $form];
    }
}
