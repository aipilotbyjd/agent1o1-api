<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantFollowUpStatus;
use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\Assistant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CancelFollowUpTool extends AssistantTool
{
    public const string NAME = 'cancel_follow_up';

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
        return 'Stops watching an email thread for a reply, using the id from list_follow_ups.';
    }

    protected function execute(Request $request): string
    {
        $followUp = $this->assistant->followUps()
            ->where('status', AssistantFollowUpStatus::Pending)
            ->whereKey((string) $request['id'])
            ->first();

        if ($followUp === null) {
            return 'No waiting follow-up has that id.';
        }

        $followUp->forceFill(['status' => AssistantFollowUpStatus::Cancelled, 'resolved_at' => now()])->save();

        return 'Stopped watching that thread.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
        ];
    }
}
