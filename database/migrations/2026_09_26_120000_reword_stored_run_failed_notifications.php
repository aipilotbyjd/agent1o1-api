<?php

use App\Models\Runs\Run;
use App\Notifications\Workspace\RunFailedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notifications store their text when sent, so failed-run notices sent before
 * `RunFailedNotification` named what failed still read "Run <uuid> failed".
 * This rewrites them with the current wording.
 *
 * The stored `type` is matched as a literal, and the wording comes from the
 * app's own classes only while they still offer it: if either is renamed or
 * reworked later, this becomes a no-op on a fresh database rather than
 * breaking `migrate`.
 */
return new class extends Migration
{
    private const NOTIFICATION_TYPE = 'App\Notifications\Workspace\RunFailedNotification';

    public function up(): void
    {
        if (! class_exists(Run::class)
            || ! method_exists(RunFailedNotification::class, 'titleFor')
            || ! method_exists(RunFailedNotification::class, 'bodyFor')) {
            return;
        }

        DB::table('notifications')
            ->where('type', self::NOTIFICATION_TYPE)
            ->orderBy('id')
            ->chunk(200, function ($notifications): void {
                $payloads = $notifications
                    ->mapWithKeys(fn ($notification) => [$notification->id => json_decode($notification->data, true)])
                    ->filter(fn ($payload) => is_array($payload));

                $runs = Run::query()
                    ->with('runnable')
                    ->findMany($payloads->pluck('data.run_id')->filter()->unique()->values())
                    ->keyBy('id');

                foreach ($payloads as $id => $payload) {
                    $run = $runs->get($payload['data']['run_id'] ?? null);

                    $payload['title'] = $run !== null ? RunFailedNotification::titleFor($run) : 'A run failed';
                    $payload['body'] = RunFailedNotification::bodyFor($run?->error ?? $payload['data']['error'] ?? null);

                    DB::table('notifications')->where('id', $id)->update(['data' => json_encode($payload)]);
                }
            });
    }

    public function down(): void
    {
        // The original text isn't kept, and the new wording is still accurate.
    }
};
