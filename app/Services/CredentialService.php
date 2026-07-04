<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

class CredentialService
{
    public function create(Workspace $workspace, User $creator, array $data): Credential
    {
        return Credential::create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'data' => encrypt(json_encode($data['data'] ?? [])),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function update(Credential $credential, array $data): Credential
    {
        $updates = collect($data)->only(['name', 'type', 'expires_at'])->all();

        if (isset($data['data'])) {
            $updates['data'] = encrypt(json_encode($data['data']));
        }

        $credential->update($updates);

        return $credential->fresh();
    }

    /**
     * Test a credential by hitting its configured test endpoint.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(Credential $credential): array
    {
        $data = $credential->getDecryptedData();
        $testUrl = $data['test_url'] ?? null;

        if (! $testUrl) {
            return ['ok' => true, 'message' => 'No test endpoint configured — credential stored.'];
        }

        try {
            $request = Http::timeout(15);

            $request = match ($data['type'] ?? 'bearer') {
                'bearer', 'oauth2' => $request->withToken($data['access_token'] ?? ''),
                'api_key' => $request->withHeaders([$data['header_name'] ?? 'X-Api-Key' => $data['api_key'] ?? '']),
                'basic' => $request->withBasicAuth($data['username'] ?? '', $data['password'] ?? ''),
                default => $request,
            };

            $response = $request->get($testUrl);

            $credential->update(['last_used_at' => now()]);

            return $response->successful()
                ? ['ok' => true, 'message' => 'Credential verified.']
                : ['ok' => false, 'message' => "Test request returned HTTP {$response->status()}."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => "Test request failed: {$e->getMessage()}"];
        }
    }
}
