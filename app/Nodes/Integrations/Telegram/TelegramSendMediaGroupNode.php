<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSendMediaGroupNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_media_group';
    }

    public function name(): string
    {
        return 'Telegram: Send Album';
    }

    public function description(): string
    {
        return 'Sends two to ten photos, videos, documents or audio files as one album.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'media'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'media' => Field::list('Files', 'Links or file ids, two to ten. Photos and videos can be mixed; documents and audio only with their own kind.', 'https://example.com/photo.jpg'),
                'media_type' => Field::select('Kind of file', ['photo' => 'Photos', 'video' => 'Videos', 'document' => 'Documents', 'audio' => 'Audio'], null, 'photo'),
                'caption' => Field::textarea('Caption', 'Shown under the first file. Up to 1024 characters.'),
                ...$this->sendFields(buttons: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $files = array_values(array_filter(array_map(fn (mixed $file): string => trim((string) $file), (array) ($this->decoded($config['media'] ?? null) ?? [])), fn (string $file): bool => $file !== ''));
        $type = (string) ($config['media_type'] ?? 'photo');

        if (! in_array($type, ['photo', 'video', 'document', 'audio'], true)) {
            throw new InvalidArgumentException("\"{$type}\" is not a kind of album file. Use photo, video, document or audio.");
        }

        if (count($files) < 2 || count($files) > 10) {
            throw new InvalidArgumentException('An album takes between 2 and 10 files.');
        }

        $parseMode = $this->parseMode($config['parse_mode'] ?? null);
        $caption = $this->caption($config);

        $media = array_map(fn (string $file, int $index): array => array_filter([
            'type' => $type,
            'media' => $file,
            'caption' => $index === 0 ? $caption : null,
            'parse_mode' => $index === 0 && $caption !== null ? $parseMode : null,
        ], fn (mixed $value): bool => $value !== null), $files, array_keys($files));

        $messages = (array) $this->call($run, $config, 'sendMediaGroup', ['chat_id' => $chat, 'media' => $media, ...$this->sendOptions($config, formatting: false)]);

        return ['messages' => array_map(fn (mixed $message): array => $this->shapeMessage((array) $message), $messages), 'count' => count($messages), 'chat_id' => $chat];
    }
}
