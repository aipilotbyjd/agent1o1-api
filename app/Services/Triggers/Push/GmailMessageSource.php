<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\RefreshesWatchForTrigger;
use App\Enums\Triggers\TriggerType;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Support\Carbon;

/**
 * "New email" in Gmail through Gmail's watch and a Google Pub/Sub topic.
 *
 * One watch per mailbox, shared by every Gmail trigger on it. It listens to the
 * labels those triggers ask for (the Inbox for a trigger with no label filter),
 * so a trigger on Sent mail or a custom label is pushed too. Gmail only says
 * "the mailbox changed", so the trigger's own poll fetches the new mail (and
 * applies its search). A watch lasts about a week; it is renewed daily.
 *
 * Setup outside the app: a Pub/Sub topic that Gmail may publish to
 * (gmail-api-push@system.gserviceaccount.com), and a push subscription on it
 * pointing at /api/hooks/apps/gmail?token={TRIGGERS_GMAIL_PUSH_TOKEN}.
 */
class GmailMessageSource extends AbstractWatchedSource implements RefreshesWatchForTrigger
{
    private const string API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function provider(): string
    {
        return 'gmail';
    }

    protected function nodeTypes(): array
    {
        return ['gmail_list_messages', 'gmail_list_messages_with_attachments'];
    }

    protected function isConfigured(): bool
    {
        return filled(config('triggers.push.gmail.topic')) && filled(config('triggers.push.gmail.verification_token'));
    }

    /**
     * One watch covers the whole mailbox.
     */
    public function watchKeyFor(Trigger $trigger): ?string
    {
        return 'mailbox';
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Calling `watch` again extends it.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $this->register($watch);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $this->ok($this->http($this->credentialOf($watch))->post(self::API.'/stop'));
    }

    /**
     * The mailbox address Pub/Sub notifications name.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $email = $watch?->meta['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * Whether the watch listens to every label the trigger needs.
     */
    public function watchIsStale(ProviderWatch $watch, Trigger $trigger): bool
    {
        return array_diff($this->labelsOf($trigger), (array) ($watch->meta['labels'] ?? [])) !== [];
    }

    /**
     * The labels a trigger needs to hear about: the first of its label filter
     * (a message must have all of them, so any one will do), else the Inbox.
     *
     * @return list<string>
     */
    private function labelsOf(Trigger $trigger): array
    {
        $labels = array_values(array_filter((array) ($trigger->config['node_config']['label_ids'] ?? []), fn (mixed $label): bool => is_string($label) && $label !== ''));

        return [$labels[0] ?? 'INBOX'];
    }

    /**
     * The labels of every active Gmail trigger on the watch's mailbox.
     *
     * @return list<string>
     */
    private function labelsWatched(ProviderWatch $watch): array
    {
        $labels = Trigger::query()
            ->where('credential_id', $watch->credential_id)
            ->where('type', TriggerType::Polling)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Trigger $trigger): bool => in_array($trigger->config['node_type'] ?? null, $this->nodeTypes(), true))
            ->flatMap(fn (Trigger $trigger): array => $this->labelsOf($trigger))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $labels === [] ? ['INBOX'] : $labels;
    }

    private function register(ProviderWatch $watch): void
    {
        $http = $this->http($this->credentialOf($watch));
        $labels = $this->labelsWatched($watch);

        $email = $this->ok($http->get(self::API.'/profile'))->json('emailAddress');
        $body = $this->ok($http->post(self::API.'/watch', [
            'topicName' => config('triggers.push.gmail.topic'),
            'labelIds' => $labels,
            'labelFilterBehavior' => 'INCLUDE',
        ]))->json();

        $expires = isset($body['expiration']) ? Carbon::createFromTimestampMs((int) $body['expiration']) : now()->addDays(7);

        $watch->external_id = strtolower((string) $email);
        $watch->meta = [...(array) $watch->meta, 'email' => strtolower((string) $email), 'history_id' => $body['historyId'] ?? null, 'labels' => $labels];
        $watch->expires_at = $expires;
        $watch->renew_at = min(now()->addDay(), $expires->copy()->subHours(6));
    }
}
