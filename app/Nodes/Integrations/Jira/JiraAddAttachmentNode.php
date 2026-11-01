<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class JiraAddAttachmentNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_add_attachment';
    }

    public function name(): string
    {
        return 'Jira: Add Attachment';
    }

    public function description(): string
    {
        return 'Attaches a file to an issue, from text or from base64 content (for example from a Drive or Gmail step).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue', 'filename'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
                'filename' => Field::text('File name', null, 'report.txt'),
                'content' => Field::textarea('Text content', 'The file as text. Use this or the base64 content.'),
                'content_base64' => Field::textarea('Base64 content', 'The file as base64, e.g. from a Gmail attachment step.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'filename', 'The file name');
        $base64 = trim((string) ($config['content_base64'] ?? ''));

        $bytes = $base64 !== '' ? base64_decode($base64, true) : (string) ($config['content'] ?? '');

        if ($bytes === false || $bytes === '') {
            throw new InvalidArgumentException($base64 !== '' ? 'The base64 content could not be decoded.' : 'Give the file as text or base64.');
        }

        if (strlen($bytes) > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('The file is larger than 10 MB.');
        }

        $response = $this->http($this->resolveAccessToken($run, $config))
            ->withHeaders(['X-Atlassian-Token' => 'no-check'])
            ->attach('file', $bytes, $name)
            ->post($this->url($run, $config, '/issue/'.rawurlencode($this->issueKey($config)).'/attachments'));

        $this->assertOk($response, 'attachments');

        $attachments = array_values(array_map(fn (array $file): array => $this->shapeAttachment($file), array_filter((array) $response->json(), 'is_array')));

        return ['attachments' => $attachments, 'count' => count($attachments)];
    }
}
