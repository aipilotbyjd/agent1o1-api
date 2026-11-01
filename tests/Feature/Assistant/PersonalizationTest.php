<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\StyleLearnerAgent;
use App\Enums\Assistant\AssistantFeedbackStatus;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Assistant\AssistantStyleKind;
use App\Enums\Assistant\AssistantStyleSource;
use App\Enums\Workspaces\Role;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantFeedback;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantStyleRevision;
use App\Models\User;
use App\Services\Assistant\AssistantInstructions;
use App\Services\Assistant\Personalization\StyleProfiles;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Priya']);
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);
    $this->profiles = app(StyleProfiles::class);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->reply = fn (?AssistantSession $session = null) => ($session ?? $this->session)->messages()->create([
        'role' => AssistantMessageRole::Assistant,
        'content' => 'Here is a long, detailed answer with several paragraphs.',
    ]);
});

it('keeps a revision for every change and skips unchanged saves', function () {
    $this->profiles->update($this->assistant, AssistantStyleKind::Tone, '- Be brief.', AssistantStyleSource::Owner);
    $this->profiles->update($this->assistant, AssistantStyleKind::Tone, '- Be brief.', AssistantStyleSource::Owner);
    $profile = $this->profiles->update($this->assistant, AssistantStyleKind::Tone, "- Be brief.\n- No emoji.", AssistantStyleSource::Assistant, 'Asked for no emoji');

    expect($profile->version)->toBe(2)
        ->and($profile->revisions()->pluck('source')->map->value->all())->toBe(['owner', 'assistant']);
});

it('restores an earlier version as a new one', function () {
    $this->profiles->update($this->assistant, AssistantStyleKind::Design, '- Use tables.', AssistantStyleSource::Owner);
    $this->profiles->update($this->assistant, AssistantStyleKind::Design, '- Use bullets.', AssistantStyleSource::Feedback);

    $first = AssistantStyleRevision::query()->where('version', 1)->sole();
    $profile = $this->profiles->restore($first);

    expect($profile)->body->toBe('- Use tables.')->version->toBe(3)
        ->and($profile->revisions()->latest('version')->first()->source)->toBe(AssistantStyleSource::Restore);
});

it('puts tone and design notes in the instructions', function () {
    $this->profiles->update($this->assistant, AssistantStyleKind::Tone, '- Be brief.', AssistantStyleSource::Owner);
    $this->profiles->update($this->assistant, AssistantStyleKind::Design, '- Use tables.', AssistantStyleSource::Owner);

    expect(app(AssistantInstructions::class)->for($this->assistant))
        ->toContain("How Priya likes you to write (tone notes)\n- Be brief.")
        ->toContain("How Priya likes answers laid out (design notes)\n- Use tables.");
});

it('saves a preference the owner states in chat', function () {
    AssistantAgent::fake([
        new ToolCall('call_1', 'update_style', ['kind' => 'tone', 'notes' => '- Keep answers under 3 sentences.', 'reason' => 'Asked for shorter answers']),
        'Got it: shorter answers from now on.',
    ]);

    app(AssistantLoop::class)->send($this->session, 'Please always keep answers short');

    $profile = $this->profiles->profile($this->assistant, AssistantStyleKind::Tone);
    expect($profile->body)->toBe('- Keep answers under 3 sentences.')
        ->and($profile->revisions()->sole()->source)->toBe(AssistantStyleSource::Assistant);
});

it('learns a lasting preference from a feedback comment', function () {
    StyleLearnerAgent::fake([['change' => true, 'kind' => 'design', 'notes' => '- Lead with a one-line summary.', 'reason' => 'Wants the summary first']]);
    Passport::actingAs($this->owner);
    $message = ($this->reply)();

    $this->postJson("{$this->base}/sessions/{$this->session->id}/messages/{$message->id}/feedback", ['rating' => 'down', 'comment' => 'Too long: give me the summary first'])
        ->assertSuccessful();

    $feedback = AssistantFeedback::query()->sole();
    expect($feedback->status)->toBe(AssistantFeedbackStatus::Applied)
        ->and($feedback->applied_change['kind'])->toBe('design')
        ->and($this->profiles->body($this->assistant, AssistantStyleKind::Design))->toBe('- Lead with a one-line summary.');
});

it('leaves the profiles alone when feedback is not a lasting preference', function () {
    StyleLearnerAgent::fake([['change' => false, 'kind' => 'none', 'notes' => '', 'reason' => 'A factual correction']]);
    $message = ($this->reply)();
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sessions/{$this->session->id}/messages/{$message->id}/feedback", ['rating' => 'down', 'comment' => 'The date is wrong'])
        ->assertSuccessful();

    expect(AssistantFeedback::query()->sole()->status)->toBe(AssistantFeedbackStatus::Ignored)
        ->and($this->profiles->body($this->assistant, AssistantStyleKind::Design))->toBeNull();
});

it('records a bare rating without learning from it', function () {
    StyleLearnerAgent::fake()->preventStrayPrompts();
    $message = ($this->reply)();
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sessions/{$this->session->id}/messages/{$message->id}/feedback", ['rating' => 'up'])
        ->assertSuccessful()
        ->assertJsonPath('data.feedback.rating', 'up');

    StyleLearnerAgent::assertNeverPrompted();
});

it('never learns from incognito conversations', function () {
    StyleLearnerAgent::fake()->preventStrayPrompts();
    $session = AssistantSession::factory()->incognito()->create(['assistant_id' => $this->assistant->id]);
    $message = ($this->reply)($session);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sessions/{$session->id}/messages/{$message->id}/feedback", ['rating' => 'down', 'comment' => 'Use tables'])
        ->assertSuccessful();

    StyleLearnerAgent::assertNeverPrompted();
});

it('only accepts feedback on the owner own assistant replies', function () {
    $userMessage = $this->session->messages()->create(['role' => AssistantMessageRole::User, 'content' => 'Hi']);
    Passport::actingAs($this->owner);

    $this->postJson("{$this->base}/sessions/{$this->session->id}/messages/{$userMessage->id}/feedback", ['rating' => 'up'])
        ->assertNotFound();

    $member = User::factory()->create();
    $this->workspace->members()->create(['user_id' => $member->id, 'role' => Role::Member, 'joined_at' => now()]);
    $reply = ($this->reply)();
    Passport::actingAs($member);

    $this->postJson("{$this->base}/sessions/{$this->session->id}/messages/{$reply->id}/feedback", ['rating' => 'up'])
        ->assertNotFound();
});

it('shows, edits and restores styles through the API', function () {
    Passport::actingAs($this->owner);

    $this->getJson("{$this->base}/styles")
        ->assertSuccessful()
        ->assertJsonPath('data.styles.0.kind', 'tone')
        ->assertJsonPath('data.styles.1.kind', 'design');

    $this->putJson("{$this->base}/styles/tone", ['notes' => '- Friendly.'])->assertSuccessful()->assertJsonPath('data.style.version', 1);
    $this->putJson("{$this->base}/styles/tone", ['notes' => '- Formal.'])->assertSuccessful()->assertJsonPath('data.style.version', 2);

    $first = AssistantStyleRevision::query()->where('version', 1)->sole();

    $this->postJson("{$this->base}/styles/tone/revisions/{$first->id}/restore")
        ->assertSuccessful()
        ->assertJsonPath('data.style.notes', '- Friendly.')
        ->assertJsonPath('data.style.version', 3);

    $this->postJson("{$this->base}/styles/design/revisions/{$first->id}/restore")->assertNotFound();
    $this->putJson("{$this->base}/styles/voice", ['notes' => 'x'])->assertNotFound();
});

it('includes the owner rating on messages', function () {
    $this->session->messages()->create(['role' => AssistantMessageRole::User, 'content' => 'Q']);
    $message = ($this->reply)();
    AssistantFeedback::query()->create(['assistant_id' => $this->assistant->id, 'assistant_message_id' => $message->id, 'rating' => 'up', 'status' => 'ignored']);
    Passport::actingAs($this->owner);

    $this->getJson("{$this->base}/sessions/{$this->session->id}/messages")
        ->assertSuccessful()
        ->assertJsonPath('data.0.feedback', null)
        ->assertJsonPath('data.1.feedback.rating', 'up');
});
