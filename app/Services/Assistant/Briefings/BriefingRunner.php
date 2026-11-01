<?php

namespace App\Services\Assistant\Briefings;

use App\Ai\Assistant\BriefingWriterAgent;
use App\Ai\ResponseUsage;
use App\Enums\Assistant\AssistantBriefingRunStatus;
use App\Enums\Assistant\AssistantStyleKind;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingConfig;
use App\Models\Assistant\AssistantBriefingRun;
use App\Models\Assistant\AssistantSourceCursor;
use App\Models\Connectors\ConnectorCredential;
use App\Notifications\Assistant\DailyReportNotification;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Personalization\StyleProfiles;
use App\Services\Assistant\Runtime\AssistantModel;
use App\Services\Assistant\Tools\ConnectorToolProvider;
use App\Services\Billing\CreditGate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Produces one Daily report: read each app since its own cursor (each one
 * on its own, so one failing app doesn't sink the report), write it, keep
 * the Situations, advance the cursors of the apps that were read, and
 * deliver it: exactly once.
 */
class BriefingRunner
{
    use SettlesBriefingRuns;

    public function __construct(
        private readonly BriefingSources $sources,
        private readonly ConnectorToolProvider $connectors,
        private readonly AssistantModel $model,
        private readonly StyleProfiles $styles,
        private readonly BrandRepository $brands,
        private readonly CreditGate $creditGate,
    ) {}

    public function run(AssistantBriefingRun $run): void
    {
        if (! $this->claim($run)) {
            return;
        }

        $run->refresh()->load('config.assistant.user', 'config.assistant.workspace');
        $config = $run->config;
        $assistant = $config->assistant;

        try {
            $this->creditGate->assertCanStartRun($assistant->workspace);

            $windowEnd = now();
            [$items, $results] = $this->collect($config, $assistant);

            if ($results->isEmpty()) {
                $this->finish($run, AssistantBriefingRunStatus::Failed, ['error' => 'No connected apps to read. Connect an app to get a Daily report.']);

                return;
            }

            $run->forceFill(['status' => AssistantBriefingRunStatus::Writing, 'window_end' => $windowEnd, 'source_results' => $results->values()->all()])->save();

            $written = $items->isEmpty()
                ? ['summary' => 'Nothing new since your last report.', 'catch_up' => '', 'situations' => [], 'usage' => null]
                : $this->write($config, $assistant, $items, $windowEnd);

            $run->forceFill([
                'summary' => $written['summary'],
                'document' => $written['catch_up'],
                'usage' => $written['usage'],
            ])->save();

            $this->storeSituations($run, $assistant, $written['situations']);
            $this->advanceCursors($config, $results, $windowEnd);
            $this->charge($run, $assistant, $written['usage'], 'Personal assistant Daily report');
            $this->finish($run, AssistantBriefingRunStatus::Completed);
            $this->deliver($run, $config, $assistant);
        } catch (Throwable $e) {
            report($e);
            $this->finish($run, AssistantBriefingRunStatus::Failed, ['error' => 'The report could not be written. Try Run now again in a minute.']);
        }
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<string, array<string, mixed>>}
     */
    private function collect(AssistantBriefingConfig $config, Assistant $assistant): array
    {
        $credentials = $this->connectors->credentialsFor($assistant)
            ->filter(fn (ConnectorCredential $credential, string $key): bool => $this->sources->for($key) !== null)
            ->when($config->connector_scope === 'selected', fn (Collection $credentials) => $credentials->only($config->connector_keys ?? []));

        $cursors = $config->cursors()->pluck('cursor_at', 'source');
        $limit = (int) config('assistant.briefings.max_items_per_source');
        $items = collect();
        $results = collect();

        foreach ($credentials as $source => $credential) {
            $since = isset($cursors[$source])
                ? now()->parse($cursors[$source])
                : now()->subHours((int) config('assistant.briefings.first_run_lookback_hours'));

            try {
                $found = $this->sources->for($source)->collect($assistant, $credential, $since, $limit);
                $items = $items->concat(collect($found)->map(fn (array $item): array => [...$item, 'source' => $source]));
                $results->put($source, ['source' => $source, 'name' => $credential->connector->name, 'ok' => true, 'items' => count($found)]);
            } catch (Throwable $e) {
                report($e);
                $results->put($source, ['source' => $source, 'name' => $credential->connector->name, 'ok' => false, 'error' => Str::limit($e->getMessage(), 200)]);
            }
        }

        return [$items, $results];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{summary: string, catch_up: string, situations: list<array<string, mixed>>, usage: array<string, mixed>}
     */
    private function write(AssistantBriefingConfig $config, Assistant $assistant, Collection $items, CarbonInterface $now): array
    {
        $startedAt = now();
        [$provider, $model] = $this->model->for($assistant);

        $response = (new BriefingWriterAgent($this->instructions($config, $assistant)))
            ->prompt($this->material($items, $now->copy()->tz($config->timezone())), provider: $provider, model: $model, timeout: (int) config('assistant.briefings.writer_timeout_seconds'));

        return [
            'summary' => trim((string) ($response['summary'] ?? '')),
            'catch_up' => trim((string) ($response['catch_up'] ?? '')),
            'situations' => array_values((array) ($response['situations'] ?? [])),
            'usage' => ResponseUsage::from($response, $startedAt),
        ];
    }

    private function instructions(AssistantBriefingConfig $config, Assistant $assistant): string
    {
        $name = $this->brands->current($assistant->workspace)->name;
        $person = $assistant->user->name;
        $maxSituations = (int) config('assistant.briefings.max_situations');

        $prompt = <<<PROMPT
            You are {$name}, writing {$person}'s Daily report from what happened in their connected apps.
            summary: at most two sentences and 40 words; the one thing to know, not a list. Don't restate routine calendar entries here.
            catch_up: Markdown bullet points of what changed, most important first. End each bullet with the app it came from in parentheses, e.g. "(Gmail)". Weave related items across apps into one bullet. No title, no date, no preamble. Leave out noise (newsletters, automated notifications) unless it matters.
            situations: at most {$maxSituations}, only for concrete work {$person} owns with a clear outcome you could help deliver (a reply they owe, a review requested from them, a deadline). Being mentioned or cc'd is not a situation. Each has a short title, a one-sentence summary, an optional next_step, the apps it came from (sources), and 1–4 ordered steps you could take. Return an empty list when there are none.
            Use only the material given. Never invent people, dates or numbers.
            PROMPT;

        if (filled($config->instructions)) {
            $prompt .= "\n\n{$person}'s instructions for this report:\n{$config->instructions}";
        }

        foreach (AssistantStyleKind::cases() as $kind) {
            $notes = $this->styles->body($assistant, $kind);

            if (filled($notes)) {
                $prompt .= "\n\n{$person}'s {$kind->value} preferences:\n{$notes}";
            }
        }

        return $prompt;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function material(Collection $items, CarbonInterface $localNow): string
    {
        $grouped = $items->groupBy('source')->map(fn (Collection $group, string $source): string => "## {$source}\n".$group
            ->map(fn (array $item): string => '- '.$item['title']
                .($item['at'] ? " [{$item['at']}]" : '')
                .($item['people'] !== [] ? ' - '.implode(', ', $item['people']) : '')
                .(filled($item['detail']) ? "\n  ".$item['detail'] : '')
                .($item['url'] ? "\n  {$item['url']}" : ''))
            ->implode("\n"));

        return "It is now {$localNow->toDayDateTimeString()} ({$localNow->tzName}).\n\n".$grouped->implode("\n\n");
    }

    /**
     * @param  list<array<string, mixed>>  $situations
     */
    private function storeSituations(AssistantBriefingRun $run, Assistant $assistant, array $situations): void
    {
        collect($situations)
            ->filter(fn ($situation): bool => is_array($situation) && filled($situation['title'] ?? null))
            ->take((int) config('assistant.briefings.max_situations'))
            ->each(function (array $data) use ($run, $assistant): void {
                $situation = $assistant->situations()->create([
                    'assistant_briefing_run_id' => $run->id,
                    'title' => Str::limit((string) $data['title'], 200),
                    'summary' => $data['summary'] ?? null,
                    'next_step' => $data['next_step'] ?? null,
                    'sources' => array_values(array_filter((array) ($data['sources'] ?? []), 'is_string')),
                ]);

                collect((array) ($data['steps'] ?? []))
                    ->filter(fn ($step): bool => is_string($step) && filled($step))
                    ->take(6)
                    ->values()
                    ->each(fn (string $step, int $position) => $situation->steps()->create(['position' => $position, 'body' => $step]));
            });
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $results
     */
    private function advanceCursors(AssistantBriefingConfig $config, Collection $results, CarbonInterface $windowEnd): void
    {
        $results->filter(fn (array $result): bool => $result['ok'])
            ->each(fn (array $result) => AssistantSourceCursor::query()->updateOrCreate(
                ['assistant_briefing_config_id' => $config->id, 'source' => $result['source']],
                ['cursor_at' => $windowEnd],
            ));
    }

    /**
     * Only one delivery per report, however many times this is reached:
     * the `delivered_at` claim is a conditional update.
     */
    private function deliver(AssistantBriefingRun $run, AssistantBriefingConfig $config, Assistant $assistant): void
    {
        if (! $this->claimDelivery($run)) {
            return;
        }

        $results = ['app' => true];

        if (($config->delivery['email'] ?? false) === true) {
            $assistant->user->notify(new DailyReportNotification($run->refresh(), $this->brands->current($assistant->workspace)->feature('daily')));
            $results['email'] = true;
        }

        $run->forceFill(['delivery_results' => $results])->save();
    }
}
