<?php

namespace App\Engine\Nodes\Apps;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

abstract class AppNode implements NodeHandler
{
    final public function handle(NodeInput $input): NodeResult
    {
        $operation = $input->config['operation'] ?? 'default';

        try {
            return $this->execute($operation, $input);
        } catch (Throwable $e) {
            return NodeResult::failed(class_basename($this)." failed: {$e->getMessage()}");
        }
    }

    abstract protected function execute(string $operation, NodeInput $input): NodeResult;

    protected function http(): PendingRequest
    {
        return Http::acceptJson()->asJson();
    }

    protected function httpWithAuth(NodeInput $input, string $baseUrl = ''): PendingRequest
    {
        $creds = $input->credentials ?? [];
        $request = $this->http();

        if (! empty($baseUrl)) {
            $request = $request->baseUrl($baseUrl);
        }

        return match ($creds['type'] ?? 'bearer') {
            'oauth2', 'bearer' => $request->withToken($creds['access_token'] ?? ''),
            'api_key' => $request->withHeaders([$creds['header_name'] ?? 'Authorization' => $creds['api_key'] ?? '']),
            'basic' => $request->withBasicAuth($creds['username'] ?? '', $creds['password'] ?? ''),
            default => $request,
        };
    }

    protected function success(array $data): NodeResult
    {
        return NodeResult::completed($data);
    }

    protected function fail(string $message): NodeResult
    {
        return NodeResult::failed($message);
    }
}
