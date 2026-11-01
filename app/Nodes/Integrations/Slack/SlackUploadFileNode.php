<?php

namespace App\Nodes\Integrations\Slack;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uploads a text file to channels through Slack's three-step external upload
 * (`files.upload`, the one-request call, was shut down): ask Slack for an
 * upload address, send the bytes to it, then complete the upload and share it.
 *
 * `content` is the file's text: no file storage exists yet to source a real
 * binary file from.
 */
class SlackUploadFileNode extends AbstractSlackNode
{
    public function type(): string
    {
        return 'slack_upload_file';
    }

    public function name(): string
    {
        return 'Slack: Upload File';
    }

    public function description(): string
    {
        return 'Uploads a text file to one or more Slack channels.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channels', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'channels' => Field::dynamic('Channels', 'slack.channels', 'Where to share the file.', 'C0123456789', multiple: true),
                'content' => Field::textarea('File content', 'The text content of the file.'),
                'filename' => Field::text('File name', null, 'report.txt'),
                'title' => Field::text('Title'),
                'initial_comment' => Field::textarea('Message', 'Posted alongside the file.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $content = (string) $config['content'];
        $filename = trim((string) ($config['filename'] ?? '')) ?: 'upload.txt';
        $title = trim((string) ($config['title'] ?? ''));

        $slot = $this->get($run, 'files.getUploadURLExternal', $config, ['filename' => $filename, 'length' => strlen($content)]);

        // The upload address takes the bytes as they are and needs no token.
        $sent = Http::withBody($content, 'application/octet-stream')->timeout(60)->post((string) $slot['upload_url']);

        if ($sent->failed()) {
            throw new RuntimeException('Slack API error [file upload]: the upload address answered '.$sent->status().'.');
        }

        $channels = array_values(array_filter(array_map('strval', (array) $config['channels'])));
        $comment = trim((string) ($config['initial_comment'] ?? ''));

        $completed = $this->post($run, 'files.completeUploadExternal', $config, array_filter([
            'files' => [array_filter(['id' => $slot['file_id'], 'title' => $title !== '' ? $title : null])],
            'channels' => implode(',', $channels),
            'initial_comment' => $comment !== '' ? $comment : null,
        ], fn ($value) => $value !== null && $value !== ''));

        return ['ok' => true, 'file' => $completed['files'][0] ?? ['id' => $slot['file_id']], 'files' => $completed['files'] ?? []];
    }
}
