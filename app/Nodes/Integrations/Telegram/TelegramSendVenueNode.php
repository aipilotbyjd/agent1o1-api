<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSendVenueNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_venue';
    }

    public function name(): string
    {
        return 'Telegram: Send Venue';
    }

    public function description(): string
    {
        return 'Sends a place on the map with a name and address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'latitude', 'longitude', 'title', 'address'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'latitude' => Field::text('Latitude', null, '51.5007'),
                'longitude' => Field::text('Longitude', null, '-0.1246'),
                'title' => Field::text('Name', null, 'Big Ben'),
                'address' => Field::text('Address', null, 'Westminster, London'),
                ...$this->sendFields(formatting: false, buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        $latitude = $config['latitude'] ?? null;
        $longitude = $config['longitude'] ?? null;

        if (! is_numeric($latitude) || abs((float) $latitude) > 90 || ! is_numeric($longitude) || abs((float) $longitude) > 180) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90 and longitude between -180 and 180.');
        }

        return $this->shapeMessage((array) $this->call($run, $config, 'sendVenue', [
            'chat_id' => $chat,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'title' => $this->requiredString($config, 'title', 'The name'),
            'address' => $this->requiredString($config, 'address', 'The address'),
            ...$this->sendOptions($config, formatting: false),
        ]));
    }
}
