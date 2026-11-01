<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramGetFileNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_file';
    }

    public function name(): string
    {
        return 'Telegram: Get File';
    }

    public function description(): string
    {
        return 'Looks up a file a person sent the bot (from a trigger\'s file_id) and, if asked, gives a link to download it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id'],
            'properties' => [
                ...$this->credentialFields(),
                'file_id' => Field::text('File id', 'The file_id from a message trigger. Files over 20 MB can\'t be downloaded by bots.'),
                'include_download_url' => Field::boolean('Include a download link', 'The link has the bot\'s token in it, so use it in a following step and don\'t share or store it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $file = (array) $this->call($run, $config, 'getFile', ['file_id' => $this->requiredString($config, 'file_id', 'The file id')]);
        $path = $file['file_path'] ?? null;

        return [
            'file_id' => $file['file_id'] ?? null,
            'file_unique_id' => $file['file_unique_id'] ?? null,
            'file_size' => $file['file_size'] ?? null,
            'file_path' => $path,
            'download_url' => $path !== null && filter_var($config['include_download_url'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? 'https://api.telegram.org/file/bot'.$this->resolveAccessToken($run, $config).'/'.$path
                : null,
        ];
    }
}
