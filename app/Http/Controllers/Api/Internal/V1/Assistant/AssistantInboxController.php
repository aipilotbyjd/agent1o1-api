<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Assistant\AssistantInboxDraftMode;
use App\Enums\Assistant\AssistantSessionOrigin;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\SaveInboxLabelRequest;
use App\Http\Requests\Api\Internal\V1\Assistant\UpdateInboxSettingsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantInboxConfig;
use App\Models\Assistant\AssistantInboxLabel;
use App\Models\Assistant\AssistantInboxMessage;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Inbox\InboxAccess;
use App\Services\Assistant\Inbox\InboxLabels;
use App\Services\Assistant\Inbox\MailboxFactory;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Smart Inbox: turning it on for the connected Gmail, its settings, the
 * owner's labels, and what it did with recent mail.
 */
class AssistantInboxController extends Controller
{
    use ResolvesOwnAssistant;

    /**
     * Mail providers Smart Inbox works with, by connector key.
     */
    private const array PROVIDERS = ['gmail', 'outlook'];

    private const array PROVIDER_NAMES = ['gmail' => 'Gmail', 'outlook' => 'Outlook'];

    private const int MESSAGES_SHOWN = 50;

    public function __construct(
        private InboxAccess $access,
        private InboxLabels $labels,
        private MailboxFactory $mailboxes,
        private ConnectorToolProvider $connectors,
    ) {}

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        return ApiResponse::success($this->payload($this->ownAssistant($request, $workspace)));
    }

    public function enable(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        abort_unless($this->access->planAllows($assistant), 402, 'Smart Inbox needs the Pro plan.');

        $connected = $this->connectors->credentialsFor($assistant)->only(self::PROVIDERS);
        abort_if($connected->isEmpty(), 422, 'Connect Gmail or Outlook in Apps first.');
        $provider = $request->string('provider')->toString() ?: $connected->keys()->first();
        abort_unless(in_array($provider, self::PROVIDERS, true), 422, 'Choose Gmail or Outlook.');

        $credential = $connected->get($provider);
        abort_if($credential === null, 422, 'Connect '.self::PROVIDER_NAMES[$provider].' in Apps first.');

        $config = $this->config($assistant);

        if ($config->connector_credential_id !== $credential->id) {
            // A different mailbox: start over there.
            $this->access->switchOff($config);
        }

        $config->forceFill(['connector_credential_id' => $credential->id, 'provider' => $provider])->save();
        $this->labels->seedBuiltins($config);

        try {
            $this->labels->syncToMailbox($config->refresh(), $this->mailboxes->for($config));
        } catch (Throwable $e) {
            report($e);

            $name = self::PROVIDER_NAMES[$provider];

            return ApiResponse::error("{$name} refused the labels: ".$e->getMessage()." Reconnect {$name} with permission to manage labels.", 422);
        }

        $config->forceFill(['enabled' => true, 'enabled_at' => now(), 'last_checked_at' => now(), 'last_error' => null])->save();

        return ApiResponse::success($this->payload($assistant), 'Smart Inbox is on.');
    }

    public function disable(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $this->access->switchOff($this->config($assistant));

        return ApiResponse::success($this->payload($assistant), 'Smart Inbox is off.');
    }

    public function update(UpdateInboxSettingsRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $this->config($assistant)->fill($request->validated())->save();

        return ApiResponse::success($this->payload($assistant), 'Saved.');
    }

    public function storeLabel(SaveInboxLabelRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $config = $this->config($assistant);

        abort_if($config->labels()->count() >= (int) config('assistant.inbox.max_labels'), 422, 'You can have up to '.config('assistant.inbox.max_labels').' labels.');
        abort_if($config->labels()->whereRaw('lower(name) = ?', [mb_strtolower($request->validated('name'))])->exists(), 422, 'You already have a label with that name.');

        $config->labels()->create([...$request->validated(), 'position' => (int) $config->labels()->max('position') + 1]);
        $this->syncIfOn($config);

        return ApiResponse::created($this->payload($assistant), 'Label added.');
    }

    public function updateLabel(SaveInboxLabelRequest $request, Workspace $workspace, AssistantInboxLabel $label): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        $config = $this->config($assistant);
        abort_if($label->assistant_inbox_config_id !== $config->id, 404);

        $validated = $request->validated();

        if (isset($validated['name']) && $validated['name'] !== $label->name) {
            abort_if($config->labels()->whereKeyNot($label->id)->whereRaw('lower(name) = ?', [mb_strtolower($validated['name'])])->exists(), 422, 'You already have a label with that name.');

            try {
                $this->labels->rename($label, $validated['name'], $config->enabled ? $this->mailboxes->for($config) : null);
            } catch (Throwable $e) {
                report($e);

                return ApiResponse::error('Gmail could not rename the label: '.$e->getMessage(), 422);
            }
        }

        $label->fill(collect($validated)->except('name')->all())->save();
        $this->syncIfOn($config);

        return ApiResponse::success($this->payload($assistant), 'Saved.');
    }

    public function destroyLabel(Request $request, Workspace $workspace, AssistantInboxLabel $label): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        abort_if($label->assistant_inbox_config_id !== $this->config($assistant)->id, 404);
        abort_if($label->isBuiltin(), 422, 'Built-in labels can be turned off or edited, but not deleted.');

        $label->delete();

        return ApiResponse::success($this->payload($assistant), 'Label removed. It stays on mail that already has it.');
    }

    /**
     * Turns a reply suggestion into a conversation, so the owner can refine
     * and send it with the assistant.
     */
    public function acceptSuggestion(Request $request, Workspace $workspace, AssistantInboxMessage $message, AssistantLoop $loop): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->ownAssistant($request, $workspace);
        abort_if($message->config->assistant_id !== $assistant->id || blank($message->suggestion), 404);

        $session = $assistant->sessions()->create([
            'title' => 'Reply: '.$message->subject,
            'origin' => AssistantSessionOrigin::Task,
            'last_activity_at' => now(),
        ]);

        $loop->send($session, "Help me reply to this email from {$message->from}, subject \"{$message->subject}\".\n\nTheir email (preview): {$message->snippet}\n\nYour suggested reply:\n{$message->suggestion}\n\nLet's refine it. Ask me before sending anything.");

        return ApiResponse::success(['session_id' => $session->id], 'Opened in chat.');
    }

    private function config(Assistant $assistant): AssistantInboxConfig
    {
        return $assistant->inbox()->firstOrCreate([], [
            'provider' => 'gmail',
            'draft_mode' => AssistantInboxDraftMode::Confident,
            'known_senders_only' => true,
        ]);
    }

    private function syncIfOn(AssistantInboxConfig $config): void
    {
        if (! $config->enabled) {
            return;
        }

        try {
            $this->labels->syncToMailbox($config, $this->mailboxes->for($config));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Assistant $assistant): array
    {
        $config = $this->config($assistant)->load('credential');

        return [
            'available' => [
                'plan' => $this->access->planAllows($assistant),
                'gmail_connected' => $this->connectors->credentialsFor($assistant)->has('gmail'),
                'outlook_connected' => $this->connectors->credentialsFor($assistant)->has('outlook'),
            ],
            'config' => [
                'enabled' => $config->enabled,
                'provider' => $config->provider,
                'account' => $config->credential?->name,
                'draft_mode' => $config->draft_mode->value,
                'drafting_instructions' => $config->drafting_instructions,
                'known_senders_only' => $config->known_senders_only,
                'skip_existing_labels' => $config->skip_existing_labels,
                'last_checked_at' => $config->last_checked_at,
                'last_error' => $config->last_error,
            ],
            'labels' => $config->labels()->get()->map(fn (AssistantInboxLabel $label): array => [
                'id' => $label->id,
                'name' => $label->name,
                'definition' => $label->definition,
                'color' => $label->color,
                'group' => $label->group->value,
                'enabled' => $label->enabled,
                'builtin' => $label->isBuiltin(),
            ])->values(),
            'messages' => $config->messages()->latest('received_at')->latest()->limit(self::MESSAGES_SHOWN)->get()
                ->map(fn (AssistantInboxMessage $message): array => [
                    'id' => $message->id,
                    'from' => $message->from,
                    'subject' => $message->subject,
                    'snippet' => $message->snippet,
                    'received_at' => $message->received_at,
                    'status' => $message->status->value,
                    'labels' => $message->labels ?? [],
                    'archived' => $message->archived,
                    'skipped_reason' => $message->skipped_reason,
                    'suggestion' => $message->suggestion,
                    'draft_status' => $message->draft_status?->value,
                ])->values(),
        ];
    }
}
