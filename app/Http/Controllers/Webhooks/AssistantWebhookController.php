<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Assistant\AssistantTriggerStatus;
use App\Enums\Assistant\AssistantTriggerType;
use App\Http\Controllers\Controller;
use App\Models\Assistant\AssistantTrigger;
use App\Services\Assistant\Triggers\TriggerFirer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssistantWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, TriggerFirer $firer): JsonResponse
    {
        $trigger = AssistantTrigger::query()
            ->with('assistant')
            ->where('type', AssistantTriggerType::Webhook)
            ->where('webhook_token', $token)
            ->first();

        abort_if($trigger === null, 404);

        if ($trigger->status !== AssistantTriggerStatus::Active) {
            return response()->json(['accepted' => false, 'reason' => 'This trigger is switched off.'], Response::HTTP_CONFLICT);
        }

        $session = $firer->fire($trigger, $request->json()->all() ?: $request->all());

        return response()->json(['accepted' => $session !== null, 'skipped' => $session === null], Response::HTTP_ACCEPTED);
    }
}
