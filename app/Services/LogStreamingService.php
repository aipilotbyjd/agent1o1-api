<?php

namespace App\Services;

use App\Models\LogStreamingConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogStreamingService
{
    /**
     * Ship a log payload to an external destination (best-effort).
     *
     * @param  array<string, mixed>  $payload
     */
    public function ship(LogStreamingConfig $config, array $payload): bool
    {
        if (! $config->is_active) {
            return false;
        }

        try {
            $response = Http::withHeaders($config->headers ?? [])
                ->timeout(10)
                ->post($config->endpoint, $payload);

            if ($response->successful()) {
                $config->update(['last_delivered_at' => now()]);

                return true;
            }
        } catch (Throwable $e) {
            Log::warning('Log streaming delivery failed.', ['config' => $config->id, 'error' => $e->getMessage()]);
        }

        return false;
    }
}
