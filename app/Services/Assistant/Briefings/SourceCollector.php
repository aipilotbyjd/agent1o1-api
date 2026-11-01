<?php

namespace App\Services\Assistant\Briefings;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use Carbon\CarbonInterface;

/**
 * Reads what changed in one app since a point in time, for a report.
 * Items are normalized so the writer sees every app the same way.
 */
interface SourceCollector
{
    /**
     * The connector key this collector reads (`gmail`, `slack`, …).
     */
    public function source(): string;

    /**
     * @return list<array{title: string, detail: string, at: string|null, url: string|null, people: list<string>}>
     */
    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array;
}
