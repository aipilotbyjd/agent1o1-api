<?php

namespace App\Console\Commands\Sandbox;

use App\Models\Assistant\AssistantSandbox;
use App\Services\Assistant\Computer\Sandboxes;
use Illuminate\Console\Command;

class CleanupSandboxesCommand extends Command
{
    protected $signature = 'sandbox:cleanup';

    protected $description = 'Delete conversation computers that sat idle past their timeout at the provider, in case the provider did not.';

    public function handle(Sandboxes $sandboxes): int
    {
        $cutoff = now()->subSeconds((int) config('assistant.sandbox.idle_seconds') + (int) config('sandbox.cleanup_grace_seconds'));
        $count = 0;

        AssistantSandbox::query()
            ->where(fn ($query) => $query->where('last_used_at', '<', $cutoff)->orWhere(fn ($query) => $query->whereNull('last_used_at')->where('created_at', '<', $cutoff)))
            ->each(function (AssistantSandbox $sandbox) use ($sandboxes, &$count): void {
                $sandboxes->destroy($sandbox);
                $count++;
            });

        $this->info("Deleted {$count} idle sandbox(es).");

        return self::SUCCESS;
    }
}
