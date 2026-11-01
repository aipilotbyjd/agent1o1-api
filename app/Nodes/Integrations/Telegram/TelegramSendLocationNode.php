<?php

namespace App\Nodes\Integrations\Telegram;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TelegramSendLocationNode extends AbstractTelegramNode
{
    public function type(): string
    {
        return 'telegram_send_location';
    }

    public function name(): string
    {
        return 'Telegram: Send Location';
    }

    public function description(): string
    {
        return 'Sends a point on the map.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['chat_id', 'latitude', 'longitude'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->chatField(),
                'latitude' => Field::text('Latitude', null, '51.5007'),
                'longitude' => Field::text('Longitude', null, '-0.1246'),
                ...$this->sendFields(formatting: false, buttons: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $chat = $this->chat($config);
        [$latitude, $longitude] = $this->coordinates($config);

        return $this->shapeMessage((array) $this->call($run, $config, 'sendLocation', ['chat_id' => $chat, 'latitude' => $latitude, 'longitude' => $longitude, ...$this->sendOptions($config, formatting: false)]));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: float, 1: float}
     */
    protected function coordinates(array $config): array
    {
        $latitude = $config['latitude'] ?? null;
        $longitude = $config['longitude'] ?? null;

        if (! is_numeric($latitude) || abs((float) $latitude) > 90 || ! is_numeric($longitude) || abs((float) $longitude) > 180) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90 and longitude between -180 and 180.');
        }

        return [(float) $latitude, (float) $longitude];
    }
}
