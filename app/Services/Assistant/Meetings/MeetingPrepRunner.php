<?php

namespace App\Services\Assistant\Meetings;

use App\Ai\Assistant\MeetingBriefAgent;
use App\Ai\ResponseUsage;
use App\Enums\Assistant\AssistantBriefingRunStatus;
use App\Enums\Assistant\AssistantMeetingPrepStatus;
use App\Enums\Assistant\AssistantStyleKind;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantBriefingConfig;
use App\Models\Assistant\AssistantBriefingRun;
use App\Models\Assistant\AssistantMeeting;
use App\Notifications\Assistant\MeetingBriefNotification;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Briefings\SettlesBriefingRuns;
use App\Services\Assistant\Personalization\StyleProfiles;
use App\Services\Assistant\Runtime\AssistantModel;
use App\Services\Billing\CreditGate;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Writes one Meeting Prep brief: research the guests and topic (read-only),
 * write the brief, mark the meeting prepared, deliver it once.
 */
class MeetingPrepRunner
{
    use SettlesBriefingRuns;

    public function __construct(
        private readonly MeetingResearch $research,
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

        $run->refresh()->load('config.assistant.user', 'config.assistant.workspace', 'meeting');
        $config = $run->config;
        $assistant = $config->assistant;
        $meeting = $run->meeting;

        if ($meeting === null) {
            $this->finish($run, AssistantBriefingRunStatus::Failed, ['error' => 'This meeting is no longer on your calendar.']);

            return;
        }

        try {
            $this->creditGate->assertCanStartRun($assistant->workspace);

            ['items' => $items, 'results' => $results] = $this->research->for(
                $assistant,
                $meeting,
                $config->connector_scope === 'selected' ? ($config->connector_keys ?? []) : null,
            );

            $run->forceFill(['status' => AssistantBriefingRunStatus::Writing, 'source_results' => $results->values()->all()])->save();

            $startedAt = now();
            [$provider, $model] = $this->model->for($assistant);

            $response = (new MeetingBriefAgent($this->instructions($config, $assistant)))
                ->prompt($this->material($meeting, $items), provider: $provider, model: $model, timeout: (int) config('assistant.briefings.writer_timeout_seconds'));

            $usage = ResponseUsage::from($response, $startedAt);

            $run->forceFill([
                'summary' => trim((string) ($response['summary'] ?? '')),
                'document' => trim((string) ($response['document'] ?? '')),
                'usage' => $usage,
            ])->save();

            $meeting->forceFill(['prep_status' => AssistantMeetingPrepStatus::Prepared])->save();
            $this->charge($run, $assistant, $usage, 'Personal assistant Meeting Prep');
            $this->finish($run, AssistantBriefingRunStatus::Completed);
            $this->deliver($run, $config, $assistant);
        } catch (Throwable $e) {
            report($e);
            $meeting->forceFill(['prep_status' => AssistantMeetingPrepStatus::Failed])->save();
            $this->finish($run, AssistantBriefingRunStatus::Failed, ['error' => 'The brief could not be written. Try Prepare now again in a minute.']);
        }
    }

    private function instructions(AssistantBriefingConfig $config, Assistant $assistant): string
    {
        $name = $this->brands->current($assistant->workspace)->name;
        $person = $assistant->user->name;

        $prompt = <<<PROMPT
            You are {$name}, preparing {$person} for a meeting.
            summary: two or three sentences, at most 60 words; what this meeting is about and the one thing to keep in mind.
            document: Markdown with up to four sections, in this order, leaving out any with nothing to say:
            ## Attendees, who they are and their relationship to {$person}, from the material only.
            ## Prior context: a short recap of the history with these people, then the relevant threads, decisions and risks, woven together rather than listed per app.
            ## Open questions: what is still unresolved.
            ## Talking points: what {$person} should raise.
            Don't restate the meeting's time, place or guest list. Use only the material given; never invent facts. If there is little history, say so briefly.
            PROMPT;

        if (filled($config->instructions)) {
            $prompt .= "\n\n{$person}'s instructions for meeting briefs:\n{$config->instructions}";
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
    private function material(AssistantMeeting $meeting, Collection $items): string
    {
        $guests = collect($meeting->attendees ?? [])
            ->map(fn (array $attendee): string => trim(($attendee['name'] ?? '').' <'.$attendee['email'].'>'))
            ->implode(', ');

        $found = $items->isEmpty()
            ? 'No related email or files were found.'
            : $items->groupBy('source')->map(fn (Collection $group, string $source): string => "## {$source}\n".$group
                ->map(fn (array $item): string => '- '.$item['title']
                    .(filled($item['at'] ?? null) ? " [{$item['at']}]" : '')
                    .(($item['people'] ?? []) !== [] ? ' - '.implode(', ', $item['people']) : '')
                    .(filled($item['detail'] ?? null) ? "\n  ".$item['detail'] : ''))
                ->implode("\n"))->implode("\n\n");

        return implode("\n\n", array_filter([
            "Meeting: {$meeting->title}",
            "Guests: {$guests}",
            filled($meeting->description) ? "Invitation notes:\n{$meeting->description}" : null,
            $found,
        ]));
    }

    private function deliver(AssistantBriefingRun $run, AssistantBriefingConfig $config, Assistant $assistant): void
    {
        if (! $this->claimDelivery($run)) {
            return;
        }

        $results = ['app' => true];

        if (($config->delivery['email'] ?? false) === true) {
            $assistant->user->notify(new MeetingBriefNotification($run->refresh(), $this->brands->current($assistant->workspace)->feature('meeting_prep')));
            $results['email'] = true;
        }

        $run->forceFill(['delivery_results' => $results])->save();
    }
}
