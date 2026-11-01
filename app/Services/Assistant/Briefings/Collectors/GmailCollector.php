<?php

namespace App\Services\Assistant\Briefings\Collectors;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Briefings\SourceCollector;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * New mail since the last report, skipping promotions and social mail.
 */
class GmailCollector implements SourceCollector
{
    private const int MAX_OPENED = 15;

    public function __construct(private readonly NodeReader $reader) {}

    public function source(): string
    {
        return 'gmail';
    }

    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array
    {
        $listed = $this->reader->read('gmail_list_messages', $assistant, $credential, [
            'query' => "after:{$since->getTimestamp()} -category:promotions -category:social",
            'max_results' => $limit,
        ]);

        return collect($listed['messages'] ?? [])
            ->take(self::MAX_OPENED)
            ->map(function (array $listedMessage) use ($assistant, $credential): array {
                $message = $this->reader->read('gmail_get_message', $assistant, $credential, ['message_id' => $listedMessage['id']]);
                $headers = collect($message['payload']['headers'] ?? [])->mapWithKeys(fn (array $header): array => [Str::lower($header['name'] ?? '') => $header['value'] ?? '']);

                return [
                    'title' => (string) ($headers['subject'] ?? '(no subject)'),
                    'detail' => html_entity_decode((string) ($message['snippet'] ?? ''), ENT_QUOTES),
                    'at' => isset($message['internalDate']) ? now()->setTimestamp((int) ($message['internalDate'] / 1000))->toIso8601String() : null,
                    'url' => "https://mail.google.com/mail/u/0/#all/{$listedMessage['id']}",
                    'people' => array_values(array_filter([(string) ($headers['from'] ?? '')])),
                ];
            })
            ->values()
            ->all();
    }
}
