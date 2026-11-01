<?php

namespace App\Ai;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Services\Workflows\NodeRegistry;

/**
 * The steps and connected accounts a user message picked with `/` and `@`
 * (its `context`), written out for the model next to the message. Accounts
 * are looked up again here: one may have been removed since it was sent.
 */
final class BuilderMessageReferences
{
    public static function describe(WorkflowBuilderMessage $message): string
    {
        $context = $message->context ?? [];
        $registry = app(NodeRegistry::class);

        $steps = collect($context['nodes'] ?? [])
            ->map(fn (string $type): ?array => $registry->describe($type))
            ->filter()
            ->map(fn (array $node): string => "- {$node['type']} ({$node['name']})");

        $accounts = ConnectorCredential::query()
            ->with('connector')
            ->where('workspace_id', $message->session->workspace_id)
            ->whereIn('id', $context['credentials'] ?? [])
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->when($message->user_id !== null, fn ($query) => $query->orWhere('created_by', $message->user_id)))
            ->get()
            ->map(fn (ConnectorCredential $credential): string => sprintf(
                '- "%s", a %s account: credential_id %s',
                $credential->name,
                $credential->connector?->key ?? 'unknown',
                $credential->id,
            ));

        if ($steps->isEmpty() && $accounts->isEmpty()) {
            return '';
        }

        return collect([
            '<referenced>',
            $steps->isNotEmpty() ? "Steps the user asked for:\n".$steps->implode("\n") : null,
            $accounts->isNotEmpty() ? "Accounts the user chose (set each matching node's config credential_id to it):\n".$accounts->implode("\n") : null,
            '</referenced>',
        ])->filter()->implode("\n");
    }
}
