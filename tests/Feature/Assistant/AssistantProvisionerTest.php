<?php

use App\Models\Assistant\Assistant;
use App\Models\User;
use App\Services\Assistant\AssistantProvisioner;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
});

it('creates the assistant on first open and reuses it afterwards', function () {
    $provisioner = app(AssistantProvisioner::class);

    $first = $provisioner->forMember($this->workspace, $this->owner);
    $second = $provisioner->forMember($this->workspace, $this->owner);

    expect($second->id)->toBe($first->id)
        ->and(Assistant::query()->count())->toBe(1);
});

it('gives each member of a workspace their own assistant', function () {
    $member = User::factory()->create();
    $provisioner = app(AssistantProvisioner::class);

    $ownerAssistant = $provisioner->forMember($this->workspace, $this->owner);
    $memberAssistant = $provisioner->forMember($this->workspace, $member);

    expect($memberAssistant->id)->not->toBe($ownerAssistant->id)
        ->and($memberAssistant->user_id)->toBe($member->id);
});

it('gives the same user a separate assistant per workspace', function () {
    $other = app(WorkspaceService::class)->create($this->owner, ['name' => 'Side project']);
    $provisioner = app(AssistantProvisioner::class);

    expect($provisioner->forMember($other, $this->owner)->id)
        ->not->toBe($provisioner->forMember($this->workspace, $this->owner)->id);
});
