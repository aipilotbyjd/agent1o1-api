<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\NotificationChannel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    /**
     * Create an in-app notification for a user.
     *
     * @param  array<string, mixed>  $data
     */
    public function notify(User $user, string $type, string $title, ?string $body = null, array $data = [], ?Workspace $workspace = null): InAppNotification
    {
        return InAppNotification::create([
            'workspace_id' => $workspace?->id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
        ]);
    }

    /**
     * Deliver a message to a configured workspace channel (Slack, Discord, webhook, SMS).
     *
     * @return array{ok: bool, message: string}
     */
    public function deliverToChannel(NotificationChannel $channel, string $message, array $payload = []): array
    {
        if (! $channel->is_active) {
            return ['ok' => false, 'message' => 'Channel is inactive.'];
        }

        try {
            $config = $channel->config;

            $response = match ($channel->type) {
                'slack' => Http::post($config['url'], ['text' => $message]),
                'discord' => Http::post($config['url'], ['content' => $message]),
                'webhook' => Http::withHeaders($config['headers'] ?? [])
                    ->post($config['url'], ['message' => $message, 'payload' => $payload]),
                'sms' => $this->sendSms($config, $message),
                default => null,
            };

            if ($response === null) {
                return ['ok' => false, 'message' => "Unsupported channel type: {$channel->type}."];
            }

            return $response->successful()
                ? ['ok' => true, 'message' => 'Delivered.']
                : ['ok' => false, 'message' => "Delivery failed: HTTP {$response->status()}."];
        } catch (Throwable $e) {
            Log::warning('Notification channel delivery failed.', ['channel' => $channel->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => "Delivery error: {$e->getMessage()}"];
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sendSms(array $config, string $message): Response
    {
        // Twilio-style REST delivery; credentials live on the channel config.
        return Http::asForm()
            ->withBasicAuth($config['account_sid'] ?? '', $config['auth_token'] ?? '')
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['account_sid']}/Messages.json", [
                'To' => $config['to'] ?? '',
                'From' => $config['from'] ?? '',
                'Body' => $message,
            ]);
    }
}
