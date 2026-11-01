<?php

namespace App\Services\SuperAdmin\Models;

use App\Models\Ai\AiEndpoint;
use App\Models\Ai\ModelRoute;
use App\Models\SuperAdmin\SuperAdmin;
use App\Services\Ai\EndpointUrlGuard;
use App\Services\Ai\OperatorEndpoints;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * The operator's own AI endpoints. A key is stored encrypted and never
 * appears anywhere: the audit log records only *that* it was set or changed.
 * Every change is registered with this process straight away, so a worker
 * that already knows the endpoint picks it up without waiting.
 */
class SuperAdminEndpointService
{
    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly OperatorEndpoints $registry,
        private readonly EndpointUrlGuard $guard,
    ) {}

    /**
     * @param  array{slug: string, name: string, base_url: string, api_key?: ?string, notes?: ?string, is_enabled?: bool, workspace_requests_per_minute?: ?int, total_requests_per_minute?: ?int}  $data
     */
    public function create(SuperAdmin $actor, array $data, string $reason): AiEndpoint
    {
        return DB::transaction(function () use ($actor, $data, $reason): AiEndpoint {
            $endpoint = AiEndpoint::query()->create([...$data, 'is_enabled' => $data['is_enabled'] ?? true]);

            $this->audit->record($actor, 'models.endpoint.created', $endpoint, null, $this->snapshot($endpoint), $reason);
            $this->registry->refresh($endpoint->slug);

            return $endpoint;
        });
    }

    /**
     * @param  array{name?: string, base_url?: string, api_key?: ?string, clear_api_key?: bool, notes?: ?string, is_enabled?: bool, workspace_requests_per_minute?: ?int, total_requests_per_minute?: ?int}  $data
     */
    public function update(SuperAdmin $actor, AiEndpoint $endpoint, array $data, string $reason): AiEndpoint
    {
        $clear = (bool) ($data['clear_api_key'] ?? false);
        unset($data['clear_api_key']);

        $replacesKey = array_key_exists('api_key', $data) && filled($data['api_key']);

        if (! $replacesKey) {
            unset($data['api_key']);
        }

        if ($clear) {
            $data['api_key'] = null;
        }

        return DB::transaction(function () use ($actor, $endpoint, $data, $reason, $replacesKey, $clear): AiEndpoint {
            $before = $this->snapshot($endpoint);

            $endpoint->fill($data)->save();

            $after = [...$this->snapshot($endpoint), 'key_changed' => $replacesKey || $clear];

            $this->audit->record($actor, 'models.endpoint.updated', $endpoint, $before, $after, $reason);
            $this->registry->refresh($endpoint->slug);

            return $endpoint;
        });
    }

    /**
     * An endpoint a route still uses cannot be deleted: switch it off, or move
     * the routes first.
     */
    public function delete(SuperAdmin $actor, AiEndpoint $endpoint, string $reason): void
    {
        if (ModelRoute::query()->where('execution_provider', $endpoint->slug)->exists()) {
            throw new HttpException(409, 'Routes still use this endpoint. Remove or move them first, or switch the endpoint off instead.');
        }

        DB::transaction(function () use ($actor, $endpoint, $reason): void {
            $this->audit->record($actor, 'models.endpoint.deleted', $endpoint, $this->snapshot($endpoint), null, $reason);

            $endpoint->delete();
        });

        $this->registry->refresh($endpoint->slug);
    }

    /**
     * Asks the endpoint for its model list (`GET {base_url}/models`, which
     * every OpenAI-compatible server answers) and keeps the outcome. The
     * address is checked again first, and redirects are not followed.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(SuperAdmin $actor, AiEndpoint $endpoint): array
    {
        $problem = $this->guard->problem($endpoint->base_url);

        $result = $problem !== null ? ['ok' => false, 'message' => $problem] : $this->ping($endpoint);

        $endpoint->forceFill([
            'last_checked_at' => now(),
            'last_check_ok' => $result['ok'],
            'last_check_message' => Str::limit($result['message'], 500, ''),
        ])->save();

        $this->audit->record($actor, 'models.endpoint.tested', $endpoint, null, ['slug' => $endpoint->slug, 'ok' => $result['ok']]);

        return $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function ping(AiEndpoint $endpoint): array
    {
        try {
            $request = Http::timeout(10)->withoutRedirecting()->acceptJson();

            if (filled($endpoint->api_key)) {
                $request = $request->withToken($endpoint->api_key);
            }

            $response = $request->get(rtrim($endpoint->base_url, '/').'/models');
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not connect: '.Str::limit($e->getMessage(), 200, '')];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => 'The endpoint answered.']
            : ['ok' => false, 'message' => "The endpoint answered HTTP {$response->status()}."];
    }

    /**
     * What the audit log may keep: everything except the key.
     *
     * @return array<string, mixed>
     */
    private function snapshot(AiEndpoint $endpoint): array
    {
        return [
            'slug' => $endpoint->slug,
            'name' => $endpoint->name,
            'base_url' => $endpoint->base_url,
            'is_enabled' => $endpoint->is_enabled,
            'workspace_requests_per_minute' => $endpoint->workspace_requests_per_minute,
            'total_requests_per_minute' => $endpoint->total_requests_per_minute,
            'has_key' => filled($endpoint->api_key),
        ];
    }
}
