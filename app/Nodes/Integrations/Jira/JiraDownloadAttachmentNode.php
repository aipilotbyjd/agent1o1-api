<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class JiraDownloadAttachmentNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_download_attachment';
    }

    public function name(): string
    {
        return 'Jira: Download Attachment';
    }

    public function description(): string
    {
        return 'Downloads a file attached to an issue as base64 (and as text when it is a text file).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['attachment_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'attachment_id' => Field::text('Attachment id', 'The "id" of an attachment from Get Issue.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->requiredString($config, 'attachment_id', 'The attachment id');
        $meta = $this->request($run, $config, 'GET', '/attachment/'.rawurlencode($id));

        if ((int) ($meta['size'] ?? 0) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('The attachment is larger than 10 MB.');
        }

        $response = $this->http($this->resolveAccessToken($run, $config))->accept('*/*')->get($this->url($run, $config, '/attachment/content/'.rawurlencode($id)));
        $this->assertOk($response, 'attachment content');

        $bytes = $response->body();
        $mime = (string) ($meta['mimeType'] ?? '');

        return [
            ...$this->shapeAttachment($meta),
            'content_base64' => base64_encode($bytes),
            'content' => str_starts_with($mime, 'text/') || $mime === 'application/json' ? $bytes : null,
        ];
    }
}
