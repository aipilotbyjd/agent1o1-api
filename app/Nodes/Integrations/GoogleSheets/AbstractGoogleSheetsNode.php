<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared request/error-handling for the Google Sheets node family. Resolves
 * the access token via `ResolvesConnectorCredential`: the same
 * workspace-scoped `ConnectorCredential` (`gmail` connector, whose OAuth
 * scopes cover every Google product node) used by `AbstractGmailNode`.
 */
abstract class AbstractGoogleSheetsNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    private const string BASE_URL = 'https://sheets.googleapis.com/v4';

    public function category(): string
    {
        return 'google_sheets';
    }

    /**
     * `/spreadsheets/{id}/values/{range}` with both segments URL-encoded: a
     * tab name may hold spaces, quotes or `#` (`'Q1 Sales'!A1:B2`). `!` and
     * `:` are left readable; Sheets accepts them either way.
     *
     * @param  array<string, mixed>  $config
     */
    protected function valuesPath(array $config): string
    {
        $range = str_replace(['%21', '%3A'], ['!', ':'], rawurlencode((string) $config['range']));

        return '/spreadsheets/'.rawurlencode((string) $config['spreadsheet_id'])."/values/{$range}";
    }

    /**
     * `/spreadsheets/{id}` with the ID URL-encoded.
     *
     * @param  array<string, mixed>  $config
     */
    protected function spreadsheetPath(array $config): string
    {
        return '/spreadsheets/'.rawurlencode((string) $config['spreadsheet_id']);
    }

    /**
     * The numeric ID of a tab, given its name or the A1 range the sheet
     * dropdown produces (`'Q1 Sales'!A1:B2`, `Sheet1`).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException when the spreadsheet has no such tab
     */
    protected function sheetIdFor(Run $run, array $config, string $tab): int
    {
        $name = self::tabName($tab);
        $spreadsheet = $this->get($run, $this->spreadsheetPath($config), $config, [
            'fields' => 'sheets.properties(sheetId,title)',
        ]);

        foreach ((array) ($spreadsheet['sheets'] ?? []) as $sheet) {
            if (($sheet['properties']['title'] ?? null) === $name) {
                return (int) $sheet['properties']['sheetId'];
            }
        }

        throw new RuntimeException("Google Sheets: the spreadsheet has no tab named \"{$name}\".");
    }

    /**
     * The tab name inside an A1 range: the part before `!`, without the
     * quotes Sheets puts around names with spaces (and with `''` read as `'`).
     */
    public static function tabName(string $range): string
    {
        $tab = str_contains($range, '!') ? substr($range, 0, (int) strrpos($range, '!')) : $range;

        if (strlen($tab) >= 2 && $tab[0] === "'" && str_ends_with($tab, "'")) {
            return str_replace("''", "'", substr($tab, 1, -1));
        }

        return $tab;
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
    protected function put(Run $run, string $endpoint, array $config, array $params = []): array
    {
        return $this->call($run, 'put', $endpoint, $config, $params);
    }

    /**
     * Sheets signals failure via a normal HTTP status code, with the error
     * detail under `error.message` in the JSON body, same as Gmail/Drive.
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
            'put' => $request->asJson()->put(self::BASE_URL.$endpoint, $params),
            // Some endpoints (clear) require a body-less POST: `[]` would be refused.
            default => $params === []
                ? $request->withBody('', 'application/json')->post(self::BASE_URL.$endpoint)
                : $request->asJson()->post(self::BASE_URL.$endpoint, $params),
        };

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Google Sheets API error [{$endpoint}]: {$message}");
        }

        return $response->json() ?? [];
    }
}
