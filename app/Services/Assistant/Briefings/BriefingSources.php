<?php

namespace App\Services\Assistant\Briefings;

use App\Services\Assistant\Briefings\Collectors\GitHubCollector;
use App\Services\Assistant\Briefings\Collectors\GmailCollector;
use App\Services\Assistant\Briefings\Collectors\GoogleCalendarCollector;
use App\Services\Assistant\Briefings\Collectors\GoogleDriveCollector;
use App\Services\Assistant\Briefings\Collectors\OutlookCollector;
use App\Services\Assistant\Briefings\Collectors\SlackCollector;
use Illuminate\Contracts\Container\Container;

/**
 * Which apps a report can read, keyed by connector key. An app without a
 * collector is simply skipped by reports (it still works in chat).
 */
class BriefingSources
{
    /**
     * @var list<class-string<SourceCollector>>
     */
    private const array COLLECTORS = [
        GmailCollector::class,
        GoogleCalendarCollector::class,
        SlackCollector::class,
        GitHubCollector::class,
        GoogleDriveCollector::class,
        OutlookCollector::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(string $source): ?SourceCollector
    {
        return $this->all()[$source] ?? null;
    }

    /**
     * @return array<string, SourceCollector>
     */
    public function all(): array
    {
        return collect(self::COLLECTORS)
            ->map(fn (string $class): SourceCollector => $this->container->make($class))
            ->keyBy(fn (SourceCollector $collector): string => $collector->source())
            ->all();
    }
}
