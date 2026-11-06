<?php

namespace App\Jobs\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Queue;
use App\Models\Ai\AiProviderCredential;
use App\Models\Workspaces\Workspace;
use App\Notifications\Ai\AiProviderAvailabilityNotification;
use App\Services\Ai\AiProviderSettings;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Tells every workspace with a key for a provider that a super admin turned
 * customer keys for it off, or back on: the owners and admins for a team
 * key, and whoever added each key (so a personal key's owner hears too).
 * One notice per member per workspace, however many keys they have.
 */
class NotifyAiProviderAvailabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $provider,
        public bool $available,
        public bool $sendEmail,
        public ?string $message = null,
    ) {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(NotificationDispatcher $notifications, AiProviderSettings $settings): void
    {
        $label = $settings->label($this->provider);

        AiProviderCredential::query()
            ->where('execution_provider', $this->provider)
            ->with(['workspace', 'creator'])
            ->get()
            ->groupBy('workspace_id')
            ->each(function (Collection $credentials) use ($notifications, $label): void {
                /** @var Workspace|null $workspace */
                $workspace = $credentials->first()->workspace;

                if ($workspace === null) {
                    return;
                }

                $recipients = collect()
                    ->when(
                        $credentials->contains(fn (AiProviderCredential $credential): bool => $credential->scope === ConnectorCredentialScope::Team),
                        fn (Collection $recipients): Collection => $recipients->merge($notifications->ownersAndAdmins($workspace)),
                    )
                    ->merge($credentials->pluck('creator'))
                    ->filter()
                    ->unique('id')
                    ->values();

                $notifications->dispatch($recipients, new AiProviderAvailabilityNotification(
                    $workspace,
                    $label,
                    $this->available,
                    $this->message,
                    $this->sendEmail,
                ));
            });
    }
}
