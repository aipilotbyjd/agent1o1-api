<?php

namespace App\Nodes\Integrations\CalCom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class CalComCancelBookingNode extends AbstractCalComNode
{
    public function type(): string
    {
        return 'cal_com_cancel_booking';
    }

    public function name(): string
    {
        return 'Cal.com: Cancel Booking';
    }

    public function description(): string
    {
        return 'Cancels a booking and tells the attendees why.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['booking_id'],
            'properties' => [
                ...$this->credentialFields(),
                'booking_id' => Field::text('Booking ID', 'From List Bookings or Create Booking.', 'abc123XYZ'),
                'reason' => Field::textarea('Reason', 'Shown to the attendees.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = trim((string) ($config['booking_id'] ?? ''));

        if (preg_match('/^[A-Za-z0-9_-]{6,64}$/', $id) !== 1) {
            throw new InvalidArgumentException('That is not a booking ID. Use the ID from List Bookings.');
        }

        $this->call($run, $config, 'POST', self::API_URL."/bookings/{$id}/cancel", array_filter(['cancellationReason' => filled($config['reason'] ?? null) ? mb_substr(trim((string) $config['reason']), 0, 1000) : null], fn (mixed $value): bool => $value !== null) ?: ['cancellationReason' => ''], headers: $this->version(self::BOOKINGS_VERSION));

        return ['booking_id' => $id, 'cancelled' => true];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'booking_id' => ['title' => 'Booking ID', 'description' => 'The booking cancelled.'],
                'cancelled' => ['title' => 'Cancelled', 'description' => 'True when it was cancelled.'],
            ],
        ];
    }
}
