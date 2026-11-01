<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TelegramCreateChatInviteLinkNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_create_chat_invite_link';
    }

    public function name(): string
    {
        return 'Telegram: Create Invite Link';
    }

    public function description(): string
    {
        return 'Creates an invite link for a group or channel, with an optional name, expiry, member limit or join approval.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'name' => Field::text('Name', 'Only admins see it. Up to 32 characters.'),
                'expires_in_hours' => Field::integer('Expires after (hours)', 'Leave empty for a link that never expires.', null, 1, 8760),
                'member_limit' => Field::integer('Member limit', 'How many people may join with it, 1 to 99999. Not with join approval.', null, 1, 99999),
                'creates_join_request' => Field::boolean('Admins approve each join', 'People ask to join and an admin approves them. Starts "Join request" triggers.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $needsApproval = filter_var($config['creates_join_request'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($needsApproval && is_numeric($config['member_limit'] ?? null)) {
            throw new InvalidArgumentException('A member limit can\'t be used with join approval.');
        }

        $link = (array) $this->call($run, $config, 'createChatInviteLink', [
            'chat_id' => $chat,
            'name' => filled($config['name'] ?? null) ? mb_substr((string) $config['name'], 0, 32) : null,
            'expire_date' => is_numeric($config['expires_in_hours'] ?? null) ? now()->addHours((int) $config['expires_in_hours'])->getTimestamp() : null,
            'member_limit' => is_numeric($config['member_limit'] ?? null) ? (int) $config['member_limit'] : null,
            'creates_join_request' => $needsApproval ?: null,
        ]);

        return ['chat_id' => $chat, 'invite_link' => $link['invite_link'] ?? null, 'name' => $link['name'] ?? null, 'expires_at' => isset($link['expire_date']) ? Carbon::createFromTimestampUTC((int) $link['expire_date'])->toIso8601String() : null, 'member_limit' => $link['member_limit'] ?? null];
    }
}
