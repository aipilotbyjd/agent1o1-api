<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class DocumentLoaderNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'from_url' => $this->fromUrl($input),
            'from_text' => $this->success(['content' => (string) ($input->config['text'] ?? ''), 'source' => 'inline']),
            default => $this->fail("DocumentLoader: unknown operation '{$operation}'"),
        };
    }

    private function fromUrl(NodeInput $input): NodeResult
    {
        $url = $input->config['url'] ?? '';

        if (empty($url)) {
            return $this->fail('DocumentLoader: url is required');
        }

        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            return $this->fail("DocumentLoader: failed to fetch {$url} ({$response->status()})");
        }

        $content = $response->body();
        $contentType = $response->header('Content-Type');

        // Strip HTML tags for text extraction
        if (str_contains($contentType, 'text/html') && ($input->config['strip_html'] ?? true)) {
            $content = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        }

        return $this->success([
            'content' => $content,
            'source' => $url,
            'content_type' => $contentType,
            'length' => mb_strlen($content),
        ]);
    }
}
