<?php

namespace App\Services\Assistant\Inbox;

use App\Enums\Assistant\AssistantInboxLabelGroup;
use App\Models\Assistant\AssistantInboxConfig;
use App\Models\Assistant\AssistantInboxLabel;
use Illuminate\Support\Str;

/**
 * The owner's labels and their twins in the mailbox. A label whose name
 * already exists in the mailbox is adopted rather than duplicated; renaming
 * one renames the mailbox label too, so mail already labelled follows.
 */
class InboxLabels
{
    /**
     * @var list<array{key: string, name: string, definition: string, color: string, group: AssistantInboxLabelGroup}>
     */
    private const array BUILTINS = [
        ['key' => 'needs_reply', 'name' => 'Needs reply', 'definition' => 'The sender is waiting for a reply from me.', 'color' => '#ef4444', 'group' => AssistantInboxLabelGroup::Keep],
        ['key' => 'time_sensitive', 'name' => 'Time sensitive', 'definition' => 'There is an explicit deadline, date or urgency.', 'color' => '#f59e0b', 'group' => AssistantInboxLabelGroup::Keep],
        ['key' => 'waiting_on_you', 'name' => 'Waiting on you', 'definition' => 'Progress is blocked until I take an action (approve, review, sign, decide).', 'color' => '#8b5cf6', 'group' => AssistantInboxLabelGroup::Keep],
        ['key' => 'fyi', 'name' => 'FYI', 'definition' => 'Useful information for me, but no action is needed.', 'color' => '#3b82f6', 'group' => AssistantInboxLabelGroup::Keep],
        ['key' => 'low_priority', 'name' => 'Low priority', 'definition' => 'Not urgent and of limited value right now: newsletters, promotions, automated notifications.', 'color' => '#6b7280', 'group' => AssistantInboxLabelGroup::MoveOut],
    ];

    public function seedBuiltins(AssistantInboxConfig $config): void
    {
        foreach (self::BUILTINS as $position => $label) {
            $config->labels()->firstOrCreate(['key' => $label['key']], [...$label, 'position' => $position, 'enabled' => true]);
        }
    }

    /**
     * Makes sure every enabled label exists in the mailbox.
     */
    public function syncToMailbox(AssistantInboxConfig $config, Mailbox $mailbox): void
    {
        $existing = collect($mailbox->labels())->keyBy(fn (array $label): string => Str::lower($label['name']));

        $config->labels()->where('enabled', true)->get()
            ->each(function (AssistantInboxLabel $label) use ($mailbox, $existing): void {
                if ($label->provider_label_id !== null && $existing->contains('id', $label->provider_label_id)) {
                    return;
                }

                $match = $existing->get(Str::lower($label->name));

                $label->forceFill(['provider_label_id' => $match['id'] ?? $mailbox->createLabel($label->name)])->save();
            });
    }

    public function rename(AssistantInboxLabel $label, string $name, ?Mailbox $mailbox): void
    {
        $providerLabelId = $label->provider_label_id;

        if ($mailbox !== null && $providerLabelId !== null && $label->name !== $name) {
            $providerLabelId = $mailbox->renameLabel($providerLabelId, $name);
        }

        $label->forceFill(['name' => $name, 'provider_label_id' => $providerLabelId])->save();
    }

    /**
     * Forget the links to mailbox labels (the labels themselves stay on
     * the mail), on disconnect, or when switching mailboxes.
     */
    public function unlink(AssistantInboxConfig $config): void
    {
        $config->labels()->update(['provider_label_id' => null]);
    }
}
