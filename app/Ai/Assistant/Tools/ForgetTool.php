<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deletes a fact saved with `remember`, when the owner says it's wrong or
 * asks the assistant to forget it.
 */
class ForgetTool extends AssistantTool
{
    public const string NAME = 'forget';

    public function __construct(private readonly Assistant $assistant) {}

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
        return 'Deletes a saved fact by its key, when the person says it is wrong or asks you to forget it.';
    }

    protected function execute(Request $request): string
    {
        $key = (string) $request['key'];

        $deleted = $this->assistant->memories()->where('key', $key)->delete();

        return $deleted > 0 ? "Forgot {$key}." : "Nothing was saved under {$key}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required(),
        ];
    }
}
