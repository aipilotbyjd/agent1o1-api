<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class TelegramGetChatMemberNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_get_chat_member';
    }

    public function name(): string
    {
        return 'Telegram: Get Chat Member';
    }

    public function description(): string
    {
        return 'Gets one person\'s status in a chat: member, admin, creator, restricted, left or banned.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'user_id' => Field::integer('Person id', 'Their numeric Telegram id.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $member = (array) $this->call($run, $config, 'getChatMember', ['chat_id' => $chat, 'user_id' => $this->requiredInt($config, 'user_id', 'The person id')]);

        return [
            'chat_id' => $chat,
            'user_id' => data_get($member, 'user.id'),
            'username' => data_get($member, 'user.username'),
            'name' => trim((string) data_get($member, 'user.first_name').' '.(string) data_get($member, 'user.last_name')) ?: null,
            'is_bot' => data_get($member, 'user.is_bot', false),
            'status' => $member['status'] ?? null,
            'is_member' => in_array($member['status'] ?? null, ['creator', 'administrator', 'member'], true) || (($member['status'] ?? null) === 'restricted' && ($member['is_member'] ?? false) === true),
            'is_admin' => in_array($member['status'] ?? null, ['creator', 'administrator'], true),
            'custom_title' => $member['custom_title'] ?? null,
            'until_date' => isset($member['until_date']) && (int) $member['until_date'] > 0 ? Carbon::createFromTimestampUTC((int) $member['until_date'])->toIso8601String() : null,
        ];
    }
}
