<?php

namespace App\Ai\Tools\Assistant;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Saves a durable fact about the owner. Every saved fact is put in front of
 * the model on each later turn (`AssistantInstructions`).
 */
class RememberTool extends AssistantTool
{
    public const string NAME = 'remember';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly ?AssistantSession $session = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Internal;
    }

    public function description(): Stringable|string
    {
        return 'Saves or updates a durable fact about the person you work for, recalled in every future conversation. '
            .'Use it when they tell you about themselves, their work or their preferences, or ask you to remember something. '
            .'Use a short, stable snake_case key (e.g. "preferred_name") so saving again updates the fact instead of duplicating it.';
    }

    protected function execute(Request $request): string
    {
        $key = Str::limit(Str::snake(Str::squish((string) $request['key'])), (int) config('assistant.limits.memory_key_max_chars'), '');
        $value = $this->capValue(Str::squish((string) $request['value']));

        if ($key === '' || $value === '') {
            return 'Nothing was saved: both a key and a value are needed.';
        }

        $alreadyRemembered = $this->assistant->memories()->where('key', $key)->exists();

        if (! $alreadyRemembered && $this->assistant->memories()->count() >= (int) config('assistant.limits.memories_max')) {
            return 'Nothing was saved: too many facts are already remembered. Update an existing key, or ask the person to remove some.';
        }

        $this->assistant->memories()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'source_session_id' => $this->session?->id],
        );

        return "Remembered {$key}.";
    }

    /**
     * Trims a value to the configured length, ellipsis included, so the cap is
     * a real ceiling on what is stored and sent on every turn.
     */
    private function capValue(string $value): string
    {
        $max = (int) config('assistant.limits.memory_value_max_chars');

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(0, $max - 1))).'…';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required(),
            'value' => $schema->string()->required(),
        ];
    }
}
