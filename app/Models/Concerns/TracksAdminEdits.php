<?php

namespace App\Models\Concerns;

/**
 * Remembers which fields an admin changed by hand, so a seeder that refreshes
 * the catalog from code leaves those alone instead of undoing the admin's work.
 *
 * Needs an `admin_edited_fields` json column.
 */
trait TracksAdminEdits
{
    public function initializeTracksAdminEdits(): void
    {
        $this->mergeCasts(['admin_edited_fields' => 'array']);
    }

    /**
     * @param  list<string>  $fields
     */
    public function markAdminEdited(array $fields): void
    {
        $this->admin_edited_fields = array_values(array_unique([...($this->admin_edited_fields ?? []), ...$fields]));
    }

    /**
     * Seed values minus the fields an admin has taken over.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function withoutAdminEdited(array $values): array
    {
        return array_diff_key($values, array_flip($this->admin_edited_fields ?? []));
    }
}
