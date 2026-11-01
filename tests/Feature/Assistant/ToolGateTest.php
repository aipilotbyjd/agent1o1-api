<?php

use App\Ai\Assistant\Tools\AssistantTool;
use App\Ai\Assistant\Tools\RememberTool;
use App\Enums\Assistant\AssistantToolEffect;
use App\Enums\Assistant\AssistantToolRule;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantTurn;
use App\Services\Assistant\Tools\ToolGate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->assistant = Assistant::factory()->create();
    $this->session = $this->assistant->sessions()->create(['last_activity_at' => now()]);
    $this->turn = AssistantTurn::factory()->running()->create(['assistant_session_id' => $this->session->id]);

    $this->tool = fn (AssistantToolEffect $effect, ?Closure $body = null): AssistantTool => new class($effect, $body) extends AssistantTool
    {
        public int $runs = 0;

        public function __construct(private readonly AssistantToolEffect $effect, private readonly ?Closure $body) {}

        public function name(): string
        {
            return 'probe';
        }

        public function effect(): AssistantToolEffect
        {
            return $this->effect;
        }

        public function description(): string
        {
            return 'Probe.';
        }

        protected function execute(Request $request): string
        {
            $this->runs++;

            return $this->body !== null ? ($this->body)() : 'ok';
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };
});

it('refuses a tool that already failed three times this turn instead of running it again', function () {
    $gate = ToolGate::forTurn($this->turn);
    $tool = ($this->tool)(AssistantToolEffect::Read, fn () => throw new RuntimeException('bad arguments'));
    $tool->gatedBy($gate);

    foreach (range(1, 3) as $attempt) {
        expect($tool->handle(new Request([], "call-{$attempt}")))->toContain('bad arguments');
    }

    expect($tool->runs)->toBe(3)
        ->and($tool->handle(new Request([], 'call-4')))->toContain('failed several times')
        ->and($tool->runs)->toBe(3);
});

it('does not ask for approval again once a tool is refused for failing', function () {
    $gate = new ToolGate($this->turn, collect(['probe' => AssistantToolRule::Ask]));
    $tool = ($this->tool)(AssistantToolEffect::Write, fn () => throw new RuntimeException('nope'));
    $tool->gatedBy($gate);

    foreach (range(1, 3) as $attempt) {
        $tool->handle(new Request([], "call-{$attempt}"));
    }

    expect($gate->approvalFor($tool, new Request([], 'call-4')))->toBeFalse();
});

it('counts failures per tool, not across tools', function () {
    $gate = ToolGate::forTurn($this->turn);
    $failing = ($this->tool)(AssistantToolEffect::Read, fn () => throw new RuntimeException('bad'));
    $failing->gatedBy($gate);

    foreach (range(1, 3) as $attempt) {
        $failing->handle(new Request([], "call-{$attempt}"));
    }

    $remember = (new RememberTool($this->assistant, $this->session))->gatedBy($gate);

    expect($remember->handle(new Request(['key' => 'city', 'value' => 'Pune'], 'call-5')))->toBe('Remembered city.');
});

it('normalises memory keys and trims values', function () {
    $tool = new RememberTool($this->assistant, $this->session);

    $tool->handle(new Request(['key' => 'Preferred Name', 'value' => "  Priya \n Shah  "]));

    expect($this->assistant->memories()->first())
        ->key->toBe('preferred_name')
        ->value->toBe('Priya Shah');
});

it('caps how long a remembered value can be', function () {
    config(['assistant.limits.memory_value_max_chars' => 20]);

    (new RememberTool($this->assistant))->handle(new Request(['key' => 'bio', 'value' => str_repeat('a', 100)]));

    expect(mb_strlen($this->assistant->memories()->value('value')))->toBeLessThanOrEqual(20);
});

it('refuses new facts past the cap but still updates existing ones', function () {
    config(['assistant.limits.memories_max' => 2]);
    $tool = new RememberTool($this->assistant);

    $tool->handle(new Request(['key' => 'one', 'value' => '1']));
    $tool->handle(new Request(['key' => 'two', 'value' => '2']));

    expect($tool->handle(new Request(['key' => 'three', 'value' => '3'])))->toContain('too many facts')
        ->and($tool->handle(new Request(['key' => 'one', 'value' => 'uno'])))->toBe('Remembered one.')
        ->and($this->assistant->memories()->count())->toBe(2)
        ->and($this->assistant->memories()->where('key', 'one')->value('value'))->toBe('uno');
});

it('saves nothing when the key or value is blank', function () {
    expect((new RememberTool($this->assistant))->handle(new Request(['key' => '  ', 'value' => 'x'])))->toContain('Nothing was saved')
        ->and($this->assistant->memories()->count())->toBe(0);
});
