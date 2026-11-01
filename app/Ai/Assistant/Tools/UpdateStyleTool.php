<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantStyleKind;
use App\Enums\Assistant\AssistantStyleSource;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Services\Assistant\Personalization\StyleProfiles;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lets the assistant record a lasting preference the moment the owner
 * states one ("keep it shorter", "always use tables"): the owner can see
 * and undo every change in Personalization.
 */
class UpdateStyleTool extends AssistantTool
{
    public const string NAME = 'update_style';

    public function __construct(
        private readonly Assistant $assistant,
        private readonly StyleProfiles $profiles,
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
        return 'Saves a lasting preference about how you should answer the person. '
            .'Use "tone" for how you write (length, formality, language, emoji) and "design" for how answers are laid out (bullets, tables, headings, summaries first). '
            .'Only for preferences they state about all future answers, not one-off requests. '
            .'Pass the complete new notes: keep the existing ones (shown in your instructions) unless the person contradicts them, and add the new preference as a short bullet.';
    }

    protected function execute(Request $request): string
    {
        $kind = AssistantStyleKind::tryFrom((string) $request['kind']);

        if ($kind === null) {
            return 'Unknown style kind. Use "tone" or "design".';
        }

        $profile = $this->profiles->update(
            $this->assistant,
            $kind,
            (string) $request['notes'],
            AssistantStyleSource::Assistant,
            isset($request['reason']) ? (string) $request['reason'] : null,
        );

        return "Saved your {$kind->value} preferences (version {$profile->version}).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['tone', 'design'])->required(),
            'notes' => $schema->string()->required(),
            'reason' => $schema->string(),
        ];
    }
}
