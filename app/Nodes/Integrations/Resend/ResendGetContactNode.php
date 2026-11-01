<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;

class ResendGetContactNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_get_contact';
    }

    public function name(): string
    {
        return 'Resend: Get Contact';
    }

    public function description(): string
    {
        return 'Reads a contact by email or id. Says whether they exist.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id', 'contact'],
            'properties' => [
                ...$this->credentialFields(),
                'audience_id' => $this->audienceField(),
                'contact' => Field::text('Contact', 'Their email address or Resend id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(30)->get('https://api.resend.com/audiences/'.$this->pathPart($config, 'audience_id', 'The audience').'/contacts/'.$this->pathPart($config, 'contact', 'The contact'));

        if ($response->status() === 404) {
            return ['found' => false, 'contact' => trim((string) $config['contact'])];
        }

        $this->assertOk($response);

        return [...$this->shapeContact((array) $response->json()), 'found' => true];
    }
}
