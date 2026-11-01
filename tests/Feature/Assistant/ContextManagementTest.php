<?php

use App\Ai\Assistant\AssistantAgent;
use App\Ai\Assistant\RecapAgent;
use App\Enums\Assistant\AssistantMessageRole;
use App\Enums\Billing\CreditTransactionType;
use App\Models\Ai\ModelCatalog;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantMessage;
use App\Models\Assistant\AssistantSession;
use App\Models\Billing\CreditTransaction;
use App\Models\User;
use App\Services\Assistant\AssistantInstructions;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Runtime\ContextCompactor;
use App\Services\Assistant\Runtime\ConversationHistory;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Transcription;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);
    $this->assistant = Assistant::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id]);
    $this->session = AssistantSession::factory()->create(['assistant_id' => $this->assistant->id]);
    $this->base = "/api/v1/workspaces/{$this->workspace->id}/assistant";

    $this->exchange = function (int $pairs, ?AssistantSession $session = null): void {
        $session ??= $this->session;

        foreach (range(1, $pairs) as $i) {
            $session->messages()->create(['role' => AssistantMessageRole::User, 'content' => "Question {$i}", 'created_at' => now()->subMinutes(1000 - $i * 2)]);
            $session->messages()->create(['role' => AssistantMessageRole::Assistant, 'content' => "Answer {$i}", 'created_at' => now()->subMinutes(999 - $i * 2)]);
        }
    };

    config([
        'assistant.context.compact_after_messages' => 10,
        'assistant.context.keep_recent_messages' => 4,
    ]);
});

it('leaves a short conversation alone', function () {
    ($this->exchange)(3);

    expect(app(ContextCompactor::class)->needsCompaction($this->session))->toBeFalse();
});

it('folds older messages into a recap and keeps the recent ones word for word', function () {
    ($this->exchange)(8);
    RecapAgent::fake(['Goals: plan the launch.']);
    $compactor = app(ContextCompactor::class);

    expect($compactor->needsCompaction($this->session))->toBeTrue();

    $recap = $compactor->compact($this->session);

    expect($recap)->role->toBe(AssistantMessageRole::Recap)->content->toBe('Goals: plan the launch.')
        ->and(AssistantMessage::query()->where('compacted_into_id', $recap->id)->count())->toBe(12)
        ->and(CreditTransaction::query()->where('source_type', CreditTransactionType::AssistantTurn)->where('source_id', $recap->id)->exists())->toBeTrue();

    $history = (new ConversationHistory($this->session->refresh()))->messages();
    expect(collect($history)->pluck('content')->all())->toBe(['Question 7', 'Answer 7', 'Question 8', 'Answer 8'])
        ->and(app(AssistantInstructions::class)->for($this->assistant, $this->session))->toContain('Goals: plan the launch.');
});

it('builds each new recap on the previous one', function () {
    ($this->exchange)(8);
    RecapAgent::fake(['First recap.', 'Second recap.']);
    $compactor = app(ContextCompactor::class);
    $compactor->compact($this->session);

    ($this->exchange)(6);
    $compactor->compact($this->session);

    RecapAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Summary of what came before:\nFirst recap."));
    expect(app(AssistantInstructions::class)->recapFor($this->session))->toBe('Second recap.');
});

it('never summarizes incognito conversations', function () {
    $session = AssistantSession::factory()->incognito()->create(['assistant_id' => $this->assistant->id]);
    ($this->exchange)(8, $session);

    expect(app(ContextCompactor::class)->needsCompaction($session))->toBeFalse();
});

it('summarizes between turns once a conversation gets long', function () {
    ($this->exchange)(5);
    AssistantAgent::fake(['Answer 6']);
    RecapAgent::fake(['Recap so far.']);

    app(AssistantLoop::class)->send($this->session, 'Question 6');

    expect(app(AssistantInstructions::class)->recapFor($this->session))->toBe('Recap so far.');
});

it('reports how full the context window is', function () {
    ($this->exchange)(2);
    $catalog = ModelCatalog::query()->create([
        'slug' => 'personal-assistant', 'display_name' => 'PA', 'brand' => 'internal',
        'capabilities' => ['context_window' => 1000], 'is_active' => true, 'is_internal' => true,
    ]);
    $catalog->routes()->create(['execution_provider' => 'openai', 'execution_model_id' => 'gpt-4o', 'priority' => 0, 'is_enabled' => true]);
    Passport::actingAs($this->owner);

    $this->getJson("{$this->base}/sessions/{$this->session->id}/context")
        ->assertSuccessful()
        ->assertJsonPath('data.context.window_tokens', 1000)
        ->assertJsonStructure(['data' => ['context' => ['used_tokens', 'percent', 'compacted_messages', 'parts' => ['instructions', 'tools', 'summary', 'conversation']]]]);
});

it('flags summarized messages in the transcript', function () {
    ($this->exchange)(8);
    RecapAgent::fake(['Recap.']);
    app(ContextCompactor::class)->compact($this->session);
    Passport::actingAs($this->owner);

    $messages = $this->getJson("{$this->base}/sessions/{$this->session->id}/messages?per_page=50")->assertSuccessful()->json('data');

    expect($messages)->toHaveCount(16)
        ->and($messages[0]['compacted'])->toBeTrue()
        ->and($messages[15]['compacted'])->toBeFalse();
});

it('transcribes voice input', function () {
    config(['assistant.transcription.provider' => 'openai', 'ai.providers.openai.key' => 'test-key']);
    Transcription::fake(['Remind me to call Sam.']);
    Passport::actingAs($this->owner);

    $audio = UploadedFile::fake()->createWithContent('voice.webm', str_repeat("\x1a\x45\xdf\xa3", 64))->mimeType('audio/webm');

    $this->post("{$this->base}/transcribe", ['audio' => $audio], ['Accept' => 'application/json'])
        ->assertSuccessful()
        ->assertJsonPath('data.text', 'Remind me to call Sam.');

    $this->getJson($this->base)->assertJsonPath('data.features.voice', true);
});

it('says voice input is unavailable when no provider is set up', function () {
    config(['assistant.transcription.provider' => 'openai', 'ai.providers.openai.key' => null]);
    Passport::actingAs($this->owner);

    $this->post("{$this->base}/transcribe", ['audio' => UploadedFile::fake()->create('voice.webm', 20, 'audio/webm')], ['Accept' => 'application/json'])
        ->assertServiceUnavailable();

    $this->getJson($this->base)->assertJsonPath('data.features.voice', false);
});

it('rejects files that are not audio', function () {
    Passport::actingAs($this->owner);

    $this->post("{$this->base}/transcribe", ['audio' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});
