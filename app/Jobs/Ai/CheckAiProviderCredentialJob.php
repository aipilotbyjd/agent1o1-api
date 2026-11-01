<?php

namespace App\Jobs\Ai;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Enums\Queue;
use App\Models\Ai\AiProviderCredential;
use App\Notifications\Ai\AiProviderCredentialInvalidNotification;
use App\Services\Ai\AiProviderKeyChecker;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background re-check of a workspace's own AI provider key. A rejected key
 * is not something the SDK fails over from, so this is what takes a revoked
 * key out of rotation before it keeps failing calls. Whoever relies on it
 * is told the moment a working key stops working: its owner for a personal
 * key, the owners/admins (and whoever added it) for a team key.
 */
class CheckAiProviderCredentialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public AiProviderCredential $credential)
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(AiProviderKeyChecker $checker, NotificationDispatcher $notifications): void
    {
        $wasValid = $this->credential->isValid();

        $checker->validate($this->credential);

        if (! $wasValid || $this->credential->validation_status !== AiProviderCredentialStatus::Invalid) {
            return;
        }

        $this->credential->loadMissing(['workspace', 'creator']);

        $recipients = $this->credential->scope === ConnectorCredentialScope::Personal
            ? collect([$this->credential->creator])->filter()
            : $notifications->ownersAndAdmins($this->credential->workspace)
                ->push($this->credential->creator)
                ->filter()
                ->unique('id');

        $notifications->dispatch($recipients, new AiProviderCredentialInvalidNotification(
            $this->credential,
            $checker->label($this->credential->execution_provider),
        ));
    }
}
