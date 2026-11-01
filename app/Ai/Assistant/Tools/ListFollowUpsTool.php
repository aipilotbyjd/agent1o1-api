<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantFollowUp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListFollowUpsTool extends AssistantTool
{
    public const string NAME = 'list_follow_ups';

    public function __construct(private readonly Assistant $assistant) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Read;
    }

    public function description(): Stringable|string
    {
        return 'Lists the email threads you are watching for a reply, with their ids and reminder dates.';
    }

    protected function execute(Request $request): string
    {
        $followUps = $this->assistant->followUps()
            ->where('status', AssistantFollowUpStatus::Pending)
            ->orderBy('remind_at')
            ->get();

        if ($followUps->isEmpty()) {
            return 'No follow-ups are waiting.';
        }

        return json_encode($followUps->map(fn (AssistantFollowUp $followUp): array => [
            'id' => $followUp->id,
            'subject' => $followUp->subject,
            'note' => $followUp->note,
            'remind_at' => $followUp->remind_at->toIso8601String(),
        ])->values()->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
