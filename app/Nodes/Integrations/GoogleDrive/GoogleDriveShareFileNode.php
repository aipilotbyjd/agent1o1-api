<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleDriveShareFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_share_file';
    }

    public function name(): string
    {
        return 'Google Drive: Share File';
    }

    public function description(): string
    {
        return 'Gives a person, or anyone with the link, access to a file or folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id', 'type', 'role'],
            'properties' => [
                ...$this->credentialFields(),
                'file_id' => Field::dynamic('File', 'google_drive.files', 'Pick a file or folder, or enter a Drive file ID.', '1AbCdEfGhIjKlMnOp'),
                'type' => Field::select('Share with', [
                    'user' => 'One person',
                    'anyone' => 'Anyone with the link',
                ], null, 'user'),
                'email' => Field::text('Email address', 'Needed when sharing with one person.', 'name@example.com'),
                'role' => Field::select('Access', [
                    'reader' => 'Can view',
                    'commenter' => 'Can comment',
                    'writer' => 'Can edit',
                ], null, 'reader'),
                'send_notification' => Field::boolean('Email them', 'Send the person Google\'s sharing email.', true),
                'message' => Field::textarea('Message', 'Added to the sharing email.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = (string) ($config['type'] ?? 'user');
        $email = trim((string) ($config['email'] ?? ''));

        if ($type === 'user' && $email === '') {
            throw new \InvalidArgumentException('Google Drive: enter the email address to share with.');
        }

        $query = http_build_query([
            'supportsAllDrives' => 'true',
            'sendNotificationEmail' => $type === 'user' && ($config['send_notification'] ?? true) ? 'true' : 'false',
            ...(trim((string) ($config['message'] ?? '')) !== '' ? ['emailMessage' => $config['message']] : []),
        ]);

        return $this->post($run, '/files/'.rawurlencode((string) $config['file_id'])."/permissions?{$query}", $config, array_filter([
            'type' => $type,
            'role' => $config['role'] ?? 'reader',
            'emailAddress' => $type === 'user' ? $email : null,
        ]));
    }
}
