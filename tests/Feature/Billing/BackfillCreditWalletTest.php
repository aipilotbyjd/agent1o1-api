<?php

use App\Enums\Billing\CreditGrantSource;
use App\Models\Billing\CreditGrant;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;

/**
 * The migration that moves existing balances into the wallet, run against
 * data shaped like a live install's.
 */
function runWalletBackfill(): void
{
    (require database_path('migrations/2026_11_01_120001_backfill_credit_wallet.php'))->up();
}

it('moves each workspace\'s top-up balance into one never-expiring grant without changing it', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    Workspace::whereKey($workspace->id)->update(['topup_credits' => 750]);

    runWalletBackfill();

    $grant = CreditGrant::query()->where('workspace_id', $workspace->id)->sole();
    expect($grant->source)->toBe(CreditGrantSource::Migration)
        ->and($grant->remaining)->toBe(750)
        ->and($grant->expires_at)->toBeNull()
        ->and($workspace->fresh()->topup_credits)->toBe(750);
});

it('pins each owner\'s oldest workspace as their free workspace', function () {
    $owner = User::factory()->create();
    $this->travelTo(now()->subYear());
    $oldest = app(WorkspaceService::class)->create($owner, ['name' => 'Oldest']);
    $this->travelBack();
    app(WorkspaceService::class)->create($owner, ['name' => 'Newer']);
    User::whereKey($owner->id)->update(['free_workspace_id' => null]);

    runWalletBackfill();

    expect($owner->fresh()->free_workspace_id)->toBe($oldest->id);
});
