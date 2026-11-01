<?php

namespace App\Contracts;

use App\Models\Runs\Run;

/**
 * A reader node that can also start a workflow: Gumloop's "Activate as flow
 * trigger" on a Gmail, Slack or Sheets step.
 *
 * Switched on, the node is published as a polling trigger (TriggerNodeSync).
 * Each poll runs the node's own execute(), picks the items out of its output
 * and starts one run per item it hasn't seen. In that run the node outputs
 * the one item it fired for instead of reading again (WorkflowRunner).
 *
 * The ActsAsTrigger trait supplies the defaults.
 */
interface SupportsTriggerMode
{
    /**
     * The items in one execute() output.
     *
     * Order only decides which items survive the per-poll cap.
     *
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    public function triggerItems(array $output, array $config): array;

    /**
     * An item's stable id. A poll starts one run per id it hasn't seen.
     *
     * @param  array<string, mixed>  $item
     */
    public function triggerItemId(array $item): string;

    /**
     * The payload a new item starts its run with.
     *
     * The item itself, or more detail fetched for it when the list call
     * returns only ids (as Gmail's does).
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function expandTriggerItem(Run $run, array $config, array $item): array;

    /**
     * The node's name in Triggers & Events, named for the event rather than
     * the read: "Gmail: New email", not "Gmail: List Messages".
     */
    public function triggerName(): string;

    /**
     * The node's description in Triggers & Events.
     */
    public function triggerDescription(): string;

    /**
     * The default minutes between polls: short for inboxes and chat, longer
     * for things that change rarely.
     */
    public function triggerPollMinutes(): int;

    /**
     * The config a poll reads with, given the node's own.
     *
     * A poll sees only what one read returns, so a node whose default page is
     * small widens it here: anything past the page between two polls would
     * never be seen.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function triggerReadConfig(array $config): array;

    /**
     * Whether the node lists the newest item first.
     *
     * A poll starts runs oldest first, so a burst of items is processed in the
     * order it happened.
     */
    public function triggerListsNewestFirst(): bool;
}
