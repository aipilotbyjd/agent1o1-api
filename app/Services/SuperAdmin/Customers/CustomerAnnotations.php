<?php

namespace App\Services\SuperAdmin\Customers;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAccountNote;
use App\Models\SuperAdmin\SuperAdminAccountTag;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Notes and tags that admins keep on a customer, for each other. Writing one
 * is audited; the note's text is not copied into the audit log, only its id.
 */
class CustomerAnnotations
{
    public const MAX_TAGS = 8;

    public function __construct(private readonly SuperAdminAuditLogger $audit) {}

    /**
     * @return Collection<int, SuperAdminAccountNote>
     */
    public function notes(Model $subject): Collection
    {
        return SuperAdminAccountNote::query()
            ->with('author:id,name,email')
            ->where('subject_type', CustomerSubjects::label($subject))
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function tags(Model $subject): array
    {
        return $this->tagsFor(CustomerSubjects::label($subject), [$subject->getKey()])[$subject->getKey()] ?? [];
    }

    /**
     * The tags of many records at once (one query, for a list).
     *
     * @param  iterable<string>  $ids
     * @return array<string, list<string>>
     */
    public function tagsFor(string $type, iterable $ids): array
    {
        $ids = collect($ids)->all();

        if ($ids === []) {
            return [];
        }

        return SuperAdminAccountTag::query()
            ->where('subject_type', $type)
            ->whereIn('subject_id', $ids)
            ->orderBy('tag')
            ->get(['subject_id', 'tag'])
            ->groupBy('subject_id')
            ->map(fn ($rows): array => $rows->pluck('tag')->all())
            ->all();
    }

    /**
     * Puts each record's tags on it as `account_tags`, for the list and detail resources.
     *
     * @param  iterable<Model>  $models
     */
    public function attach(string $type, iterable $models): void
    {
        $models = collect($models);
        $tags = $this->tagsFor($type, $models->map->getKey());

        $models->each(fn (Model $model) => $model->setAttribute('account_tags', $tags[$model->getKey()] ?? []));
    }

    public function addNote(SuperAdmin $actor, Model $subject, string $body): SuperAdminAccountNote
    {
        $note = SuperAdminAccountNote::query()->create([
            'subject_type' => CustomerSubjects::label($subject),
            'subject_id' => $subject->getKey(),
            'super_admin_id' => $actor->id,
            'body' => trim($body),
        ]);

        $this->audit->record($actor, 'customers.note_added', $subject, after: ['note_id' => $note->id]);

        return $note->load('author:id,name,email');
    }

    public function deleteNote(SuperAdmin $actor, Model $subject, SuperAdminAccountNote $note): void
    {
        $note->delete();

        $this->audit->record($actor, 'customers.note_deleted', $subject, before: ['note_id' => $note->id, 'author_id' => $note->super_admin_id]);
    }

    /**
     * Makes the record's tags exactly `$tags`, keeping who added the ones that stay.
     *
     * @param  list<string>  $tags  already normalised (see `normaliseTags`)
     * @return list<string>
     */
    public function syncTags(SuperAdmin $actor, Model $subject, array $tags): array
    {
        $type = CustomerSubjects::label($subject);
        $before = $this->tags($subject);

        DB::transaction(function () use ($actor, $subject, $type, $tags, $before): void {
            SuperAdminAccountTag::query()->where('subject_type', $type)->where('subject_id', $subject->getKey())->whereNotIn('tag', $tags)->delete();

            foreach (array_diff($tags, $before) as $tag) {
                SuperAdminAccountTag::query()->create(['subject_type' => $type, 'subject_id' => $subject->getKey(), 'tag' => $tag, 'super_admin_id' => $actor->id]);
            }
        });

        $after = $this->tags($subject);

        if ($before !== $after) {
            $this->audit->record($actor, 'customers.tags_changed', $subject, before: ['tags' => $before], after: ['tags' => $after]);
        }

        return $after;
    }

    /**
     * Lower case, trimmed, no duplicates, in a stable order.
     *
     * @param  list<string>  $tags
     * @return list<string>
     */
    public static function normaliseTags(array $tags): array
    {
        return collect($tags)->map(fn (string $tag): string => mb_strtolower(trim($tag)))->unique()->sort()->values()->all();
    }
}
