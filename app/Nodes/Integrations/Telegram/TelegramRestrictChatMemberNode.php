<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TelegramRestrictChatMemberNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_restrict_chat_member';
    }

    public function name(): string
    {
        return 'Telegram: Mute or Unmute Member';
    }

    public function description(): string
    {
        return 'Stops a person writing in a supergroup for a while (or for good), or gives them back the right to.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'user_id' => Field::integer('Person id'),
                'mode' => Field::select('Action', ['mute' => 'Mute', 'unmute' => 'Unmute'], null, 'mute'),
                'mute_hours' => Field::integer('Mute for (hours)', 'Leave empty to mute for good.', null, 1, 8784),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $user = $this->requiredInt($config, 'user_id', 'The person id');
        $mute = ($config['mode'] ?? 'mute') !== 'unmute';

        $this->call($run, $config, 'restrictChatMember', [
            'chat_id' => $chat,
            'user_id' => $user,
            'permissions' => $mute
                ? ['can_send_messages' => false]
                : ['can_send_messages' => true, 'can_send_audios' => true, 'can_send_documents' => true, 'can_send_photos' => true, 'can_send_videos' => true, 'can_send_video_notes' => true, 'can_send_voice_notes' => true, 'can_send_polls' => true, 'can_send_other_messages' => true, 'can_add_web_page_previews' => true],
            'until_date' => $mute && is_numeric($config['mute_hours'] ?? null) ? now()->addHours((int) $config['mute_hours'])->getTimestamp() : null,
        ]);

        return ['muted' => $mute, 'chat_id' => $chat, 'user_id' => $user];
    }
}
