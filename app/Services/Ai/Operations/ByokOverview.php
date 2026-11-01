<?php

namespace App\Services\Ai\Operations;

use App\Enums\Ai\AiProviderCredentialStatus;
use App\Enums\Ai\PlatformKeyUsage;
use App\Models\Ai\AiProviderCredential;
use App\Models\Ai\WorkspaceAiKeyPolicy;

/**
 * How workspaces are using their own keys, for an operator: how many keys
 * each provider has and in what state, which keys a provider has rejected
 * (so support can tell the customer), and how many workspaces have chosen a
 * stricter key policy. Never carries a key or its value: only the stored
 * hint (the last few characters) a workspace itself sees.
 */
class ByokOverview
{
    public const int MAX_REJECTED_LISTED = 100;

    /**
     * @return array{providers: list<array<string, mixed>>, rejected: list<array<string, mixed>>, policies: array{platform_usage: array<string, int>, personal_keys_disabled: int}}
     */
    public function report(): array
    {
        $byProvider = AiProviderCredential::query()
            ->selectRaw('execution_provider, validation_status, count(*) as total')
            ->groupBy('execution_provider', 'validation_status')
            ->get()
            ->groupBy('execution_provider');

        $providers = $byProvider->map(function ($rows, string $provider): array {
            $count = fn (AiProviderCredentialStatus $status): int => (int) ($rows->firstWhere('validation_status', $status->value)?->total ?? 0);

            return [
                'provider' => $provider,
                'label' => (string) config("byok.providers.{$provider}.label", $provider),
                'valid' => $count(AiProviderCredentialStatus::Valid),
                'invalid' => $count(AiProviderCredentialStatus::Invalid),
                'unvalidated' => $count(AiProviderCredentialStatus::Unvalidated),
                'total' => (int) $rows->sum('total'),
            ];
        })->sortBy('provider')->values()->all();

        $rejected = AiProviderCredential::query()
            ->with('workspace:id,name')
            ->where('validation_status', AiProviderCredentialStatus::Invalid->value)
            ->latest('last_validated_at')
            ->limit(self::MAX_REJECTED_LISTED)
            ->get()
            ->map(fn (AiProviderCredential $credential): array => [
                'id' => $credential->id,
                'workspace_id' => $credential->workspace_id,
                'workspace' => $credential->workspace?->name,
                'provider' => $credential->execution_provider,
                'scope' => $credential->scope->value,
                'key_hint' => $credential->key_hint,
                'message' => $credential->validation_message,
                'last_validated_at' => $credential->last_validated_at,
            ])
            ->all();

        $usage = WorkspaceAiKeyPolicy::query()
            ->selectRaw('platform_usage, count(*) as total')
            ->groupBy('platform_usage')
            ->pluck('total', 'platform_usage');

        return [
            'providers' => $providers,
            'rejected' => $rejected,
            'policies' => [
                'platform_usage' => collect(PlatformKeyUsage::cases())
                    ->mapWithKeys(fn (PlatformKeyUsage $case): array => [$case->value => (int) ($usage[$case->value] ?? 0)])
                    ->all(),
                'personal_keys_disabled' => WorkspaceAiKeyPolicy::query()->where('allow_personal_keys', false)->count(),
            ],
        ];
    }
}
