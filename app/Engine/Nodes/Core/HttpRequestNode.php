<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpRequestNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $config = $input->config;
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $config['url'] ?? '';
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;
        $timeout = (int) ($config['timeout'] ?? 30);
        $authType = $config['auth_type'] ?? 'none';

        if (empty($url)) {
            return NodeResult::failed('HTTP Request URL is required');
        }

        try {
            $request = Http::timeout($timeout)->withHeaders($headers);

            // Apply authentication
            $request = match ($authType) {
                'bearer' => $request->withToken($config['auth_token'] ?? ''),
                'basic' => $request->withBasicAuth($config['auth_user'] ?? '', $config['auth_pass'] ?? ''),
                'credential' => $this->applyCredentialAuth($request, $input->credentials),
                default => $request,
            };

            $response = match ($method) {
                'GET' => $request->get($url, $config['params'] ?? []),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url, $body ?? []),
                default => $request->get($url),
            };

            return NodeResult::completed([
                'status_code' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'headers' => $response->headers(),
                'ok' => $response->successful(),
            ]);
        } catch (Throwable $e) {
            return NodeResult::failed("HTTP request failed: {$e->getMessage()}");
        }
    }

    private function applyCredentialAuth(mixed $request, ?array $credentials): mixed
    {
        if (! $credentials) {
            return $request;
        }

        return match ($credentials['type'] ?? 'bearer') {
            'bearer' => $request->withToken($credentials['access_token'] ?? ''),
            'basic' => $request->withBasicAuth($credentials['username'] ?? '', $credentials['password'] ?? ''),
            'api_key' => $request->withHeaders([$credentials['header_name'] ?? 'X-Api-Key' => $credentials['api_key'] ?? '']),
            default => $request,
        };
    }
}
