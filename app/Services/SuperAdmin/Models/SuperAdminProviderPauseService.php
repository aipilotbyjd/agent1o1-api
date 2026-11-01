<?php

namespace App\Services\SuperAdmin\Models;

use App\Models\Ai\AiProviderPause;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The provider kill switch. Pausing stops the platform using a provider on
 * every route at once (for a leaked key, an outage, a cost runaway); resuming
 * starts it again. A workspace's own key for that provider keeps working.
 * Both are audited with the stated reason and raise an alert, because either
 * changes what every customer can use.
 */
class SuperAdminProviderPauseService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly SuperAdminModelService $models,
        private readonly SuperAdminAlerter $alerter,
    ) {}

    public function pause(SuperAdmin $actor, string $provider, string $reason): AiProviderPause
    {
        $this->assertKnown($provider);

        if (AiProviderPause::query()->where('provider', $provider)->exists()) {
            throw new HttpException(409, "[{$provider}] is already paused.");
        }

        $pause = DB::transaction(function () use ($actor, $provider, $reason): AiProviderPause {
            $pause = AiProviderPause::query()->create(['provider' => $provider, 'reason' => $reason, 'paused_at' => now()]);

            $this->audit->record($actor, 'models.provider.paused', $pause, null, ['provider' => $provider], $reason);

            return $pause;
        });

        $this->alerter->raise('provider_paused', "The provider [{$provider}] was paused: {$reason}", ['by' => $actor->email]);

        return $pause;
    }

    public function resume(SuperAdmin $actor, string $provider, string $reason): void
    {
        $pause = AiProviderPause::query()->where('provider', $provider)->first()
            ?? throw new HttpException(409, "[{$provider}] is not paused.");

        DB::transaction(function () use ($actor, $pause, $provider, $reason): void {
            $this->audit->record($actor, 'models.provider.resumed', $pause, ['provider' => $provider, 'paused_for' => $pause->reason], null, $reason);

            $pause->delete();
        });

        $this->alerter->raise('provider_paused', "The provider [{$provider}] was resumed: {$reason}", ['by' => $actor->email]);
    }

    private function assertKnown(string $provider): void
    {
        if (! in_array($provider, $this->models->knownProviders(), true)) {
            throw new HttpException(404, "There is no provider [{$provider}].");
        }
    }
}
