<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Google Drive node family. Resolves
 * the access token via `ResolvesConnectorCredential`: the same
 * workspace-scoped `ConnectorCredential` (`gmail` connector, whose OAuth
 * scopes cover every Google product node) used by `AbstractGmailNode`.
 */
abstract class AbstractGoogleDriveNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://www.googleapis.com/drive/v3';

    private const string UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    /**
     * The fields of each file in a list: enough to act on a file without a
     * second call. Drive returns only an id and a name unless told.
     */
    protected const string LIST_FIELDS = 'nextPageToken,files(id,name,mimeType,createdTime,modifiedTime,parents,webViewLink,size,trashed,owners(displayName,emailAddress))';

    /**
     * The Drive types a file can be filtered to.
     */
    protected const array FILE_TYPES = [
        'application/vnd.google-apps.folder' => 'Folders',
        'application/vnd.google-apps.document' => 'Google Docs',
        'application/vnd.google-apps.spreadsheet' => 'Google Sheets',
        'application/vnd.google-apps.presentation' => 'Google Slides',
        'application/vnd.google-apps.form' => 'Google Forms',
        'application/pdf' => 'PDFs',
        'image/*' => 'Images',
    ];

    /**
     * The file fields node outputs share.
     */
    protected const string FILE_FIELDS = 'id,name,mimeType,parents,webViewLink,modifiedTime,size';

    public function category(): string
    {
        return 'google_drive';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function get(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'get', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function post(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'post', $endpoint, $config, $params);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function patch(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'patch', $endpoint, $config, $params);
    }

    /**
     * Create a file with its content in one request (Drive's multipart
     * upload): a JSON part with the metadata, then the content.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function upload(Run $run, array $config, array $metadata, string $content, string $mimeType): array
    {
        $boundary = 'drive_'.bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode($metadata, JSON_THROW_ON_ERROR)
            ."\r\n--{$boundary}\r\nContent-Type: {$mimeType}\r\n\r\n{$content}\r\n--{$boundary}--";

        $response = Http::withToken($this->resolveAccessToken($run, $config))
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD_URL.'?uploadType=multipart&supportsAllDrives=true&fields='.rawurlencode(self::FILE_FIELDS));

        if ($response->failed()) {
            throw new RuntimeException('Google Drive API error [upload]: '.($response->json('error.message') ?? $response->body()));
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function delete(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'delete', $endpoint, $config, $params);
    }

    /**
     * The fields that narrow a file list: a folder, a type, a search. Shared
     * by every node that lists files.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function fileFilterFields(): array
    {
        return [
            'query' => Field::text('Search query', 'Drive search syntax.', "name contains 'report'"),
            'folder_id' => Field::dynamic('In folder', 'google_drive.folders', 'Only files directly inside this folder. Leave empty for all of Drive.', '1AbCdEfGhIjKlMnOp'),
            'file_type' => Field::select('File type', self::FILE_TYPES, 'Leave empty for every type.'),
            'include_trashed' => Field::advanced(Field::boolean('Include trashed files')),
        ];
    }

    /**
     * Drive's search for the filter fields, plus an optional "changed after"
     * time. Trashed files are left out unless asked for (or unless the user's
     * own query already says something about trash).
     *
     * @param  array<string, mixed>  $config
     */
    protected function fileQuery(array $config, ?string $modifiedAfter = null): string
    {
        $query = trim((string) ($config['query'] ?? ''));
        $parts = $query === '' ? [] : [$query];

        $folder = trim((string) ($config['folder_id'] ?? ''));

        if ($folder !== '') {
            $parts[] = "'".addcslashes($folder, "\\'")."' in parents";
        }

        $type = trim((string) ($config['file_type'] ?? ''));

        if ($type !== '') {
            $parts[] = str_ends_with($type, '/*')
                ? "mimeType contains '".addcslashes(substr($type, 0, -1), "\\'")."'"
                : "mimeType = '".addcslashes($type, "\\'")."'";
        }

        if ($modifiedAfter !== null) {
            $parts[] = "modifiedTime > '{$modifiedAfter}'";
        }

        if (! ($config['include_trashed'] ?? false) && ! str_contains(strtolower($query), 'trashed')) {
            $parts[] = 'trashed = false';
        }

        // The user's own query may hold an `or`: group it before adding to it.
        if (count($parts) > 1 && $query !== '') {
            $parts[0] = "({$query})";
        }

        return implode(' and ', $parts);
    }

    /**
     * Files matching the filter fields, in the order asked, with the fields
     * a flow needs.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function listFiles(Run $run, array $config, string $orderBy, ?string $modifiedAfter = null): array
    {
        return $this->get($run, '/files', $config, [
            'q' => $this->fileQuery($config, $modifiedAfter),
            'pageSize' => $config['page_size'] ?? 10,
            'orderBy' => $orderBy,
            'fields' => self::LIST_FIELDS,
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]);
    }

    /**
     * Seconds between a file's creation and its last change: how long it has
     * been edited since it was made. A new file has about none.
     *
     * @param  array<string, mixed>  $file
     */
    protected function secondsEdited(array $file): int
    {
        if (! isset($file['createdTime'], $file['modifiedTime'])) {
            return 0;
        }

        return max(0, CarbonImmutable::parse((string) $file['createdTime'])->diffInSeconds(CarbonImmutable::parse((string) $file['modifiedTime']), true));
    }

    /**
     * A file's bytes (or a Google Docs export) as text rather than JSON.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     */
    protected function getText(Run $run, string $endpoint, array $config, array $params = []): string
    {
        $response = Http::withToken($this->resolveAccessToken($run, $config))->get(self::BASE_URL.$endpoint, $params);

        if ($response->failed()) {
            throw new RuntimeException("Google Drive API error [{$endpoint}]: ".($response->json('error.message') ?? $response->body()));
        }

        return $response->body();
    }

    /**
     * Like Gmail, Drive signals failure via a normal HTTP status code, with
     * the error detail under `error.message` in the JSON body. A successful
     * delete returns an empty body, which `json()` decodes to `null`.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(Run $run, string $method, string $endpoint, array $config, array $params): array
    {
        $token = $this->resolveAccessToken($run, $config);

        $request = Http::withToken($token);
        $response = match ($method) {
            'get' => $request->get(self::BASE_URL.$endpoint, $params),
            'delete' => $request->delete(self::BASE_URL.$endpoint, $params),
            // An empty body must be `{}`: `[]` is not a JSON object and Drive refuses it.
            'patch' => $request->withBody($params === [] ? '{}' : json_encode($params), 'application/json')->patch(self::BASE_URL.$endpoint),
            default => $request->withBody($params === [] ? '{}' : json_encode($params), 'application/json')->post(self::BASE_URL.$endpoint),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Google Drive API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
