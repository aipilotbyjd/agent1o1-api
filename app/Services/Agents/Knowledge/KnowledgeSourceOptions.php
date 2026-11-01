<?php

namespace App\Services\Agents\Knowledge;

use App\Enums\Agents\KnowledgeSourceType;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Inbox\OutlookMailbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What can be picked when adding an app source (the account's Drive
 * folders, Gmail labels, Outlook folders, repos or Slack channels) so
 * nobody has to copy ids by hand. Read-only, through the chosen account.
 */
class KnowledgeSourceOptions
{
    private const int MAX_OPTIONS = 100;

    public function __construct(private readonly NodeReader $reader) {}

    /**
     * @return list<array{value: string, label: string, hint: string|null}>
     */
    public function for(KnowledgeSourceType $type, Workspace $workspace, User $user, ConnectorCredential $credential, ?string $search = null): array
    {
        $read = fn (string $node, array $config = []): array => $this->reader->readAs($node, $workspace, $user->id, $credential, $config);
        $search = trim((string) $search);

        $options = match ($type) {
            KnowledgeSourceType::GoogleDrive => collect($read('google_drive_list_files', [
                'query' => collect([
                    "mimeType = 'application/vnd.google-apps.folder'",
                    'trashed = false',
                    $search !== '' ? "name contains '".str_replace(['\\', "'"], ['', "\\'"], $search)."'" : null,
                ])->filter()->implode(' and '),
                'page_size' => self::MAX_OPTIONS,
            ])['files'] ?? [])->map(fn (array $folder): array => $this->option($folder['id'], $folder['name'] ?? 'Untitled folder')),

            KnowledgeSourceType::Gmail => collect($read('gmail_list_labels')['labels'] ?? [])
                ->filter(fn (array $label): bool => ($label['type'] ?? '') === 'user' || ($label['id'] ?? '') === 'INBOX')
                ->map(fn (array $label): array => $this->option((string) $label['name'], $label['id'] === 'INBOX' ? 'Inbox' : (string) $label['name'])),

            KnowledgeSourceType::Outlook => collect((new OutlookMailbox($credential))->folders())
                ->map(fn (array $folder): array => $this->option($folder['id'], $folder['name'], "{$folder['count']} messages")),

            KnowledgeSourceType::GitHub => collect($read('github_list_repos', ['per_page' => self::MAX_OPTIONS])['repos'] ?? [])
                ->map(fn (array $repo): array => $this->option((string) $repo['full_name'], (string) $repo['full_name'], isset($repo['description']) ? Str::limit((string) $repo['description'], 80) : null)),

            KnowledgeSourceType::Slack => collect($read('slack_list_channels', ['types' => 'public_channel,private_channel', 'limit' => 200])['channels'] ?? [])
                ->reject(fn (array $channel): bool => (bool) ($channel['is_archived'] ?? false))
                ->map(fn (array $channel): array => $this->option((string) $channel['id'], '#'.$channel['name'], isset($channel['num_members']) ? "{$channel['num_members']} members" : null)),

            KnowledgeSourceType::Url => collect(),
        };

        return $this->matching($options, $type === KnowledgeSourceType::GoogleDrive ? '' : $search);
    }

    /**
     * @param  Collection<int, array{value: string, label: string, hint: string|null}>  $options
     * @return list<array{value: string, label: string, hint: string|null}>
     */
    private function matching(Collection $options, string $search): array
    {
        return $options
            ->when($search !== '', fn (Collection $all) => $all->filter(fn (array $option): bool => Str::contains(Str::lower($option['label']), Str::lower($search))))
            ->sortBy(fn (array $option): string => Str::lower($option['label']))
            ->take(self::MAX_OPTIONS)
            ->values()
            ->all();
    }

    /**
     * @return array{value: string, label: string, hint: string|null}
     */
    private function option(string $value, string $label, ?string $hint = null): array
    {
        return ['value' => $value, 'label' => $label, 'hint' => $hint];
    }
}
