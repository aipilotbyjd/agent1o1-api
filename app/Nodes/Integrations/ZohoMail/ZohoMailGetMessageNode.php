<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZohoMailGetMessageNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_get_message';
    }

    public function name(): string
    {
        return 'Zoho Mail: Get Message';
    }

    public function description(): string
    {
        return 'Reads the content of a message. Says whether it exists.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id', 'message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => $this->folderField(),
                'message_id' => Field::text('Message id', 'From a previous step (a long number).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $folder = $this->numericId($config, 'folder_id', 'The folder');
        $id = $this->numericId($config, 'message_id', 'The message');
        $account = $this->accountId($run, $config);
        $response = $this->mailRaw($run, $config, 'GET', "accounts/{$account}/folders/{$folder}/messages/{$id}/content");

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);

        return ['found' => true, 'id' => $id, 'folder_id' => $folder, 'content' => $response->json('data.content')];
    }
}
