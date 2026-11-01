<?php

namespace App\Services\Assistant\Personalization;

use App\Enums\Assistant\AssistantStyleKind;
use App\Enums\Assistant\AssistantStyleSource;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantStyleProfile;
use App\Models\Assistant\AssistantStyleRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only way a Tone or Design profile changes. Every change (the owner's
 * edit, the assistant's on the owner's word, or one learned from feedback)
 * bumps the version and keeps a revision, so any of them can be undone.
 */
class StyleProfiles
{
    public function profile(Assistant $assistant, AssistantStyleKind $kind): AssistantStyleProfile
    {
        return AssistantStyleProfile::query()->firstOrCreate(
            ['assistant_id' => $assistant->id, 'kind' => $kind],
            ['body' => null, 'version' => 0],
        );
    }

    public function body(Assistant $assistant, AssistantStyleKind $kind): ?string
    {
        return AssistantStyleProfile::query()
            ->where('assistant_id', $assistant->id)
            ->where('kind', $kind)
            ->value('body');
    }

    /**
     * Saves a new version. An unchanged body is not a new version.
     */
    public function update(Assistant $assistant, AssistantStyleKind $kind, ?string $body, AssistantStyleSource $source, ?string $reason = null): AssistantStyleProfile
    {
        $body = $this->clean($body);

        return DB::transaction(function () use ($assistant, $kind, $body, $source, $reason): AssistantStyleProfile {
            $profile = $this->profile($assistant, $kind);
            $profile = AssistantStyleProfile::query()->lockForUpdate()->findOrFail($profile->id);

            if ($profile->body === $body) {
                return $profile;
            }

            $profile->forceFill(['body' => $body, 'version' => $profile->version + 1])->save();

            $profile->revisions()->create([
                'version' => $profile->version,
                'body' => $body,
                'source' => $source,
                'reason' => $reason === null ? null : Str::limit($reason, 250),
            ]);

            return $profile;
        });
    }

    /**
     * Restoring is itself a new version, so the history stays linear.
     */
    public function restore(AssistantStyleRevision $revision): AssistantStyleProfile
    {
        $profile = $revision->profile;

        return $this->update($profile->assistant, $profile->kind, $revision->body, AssistantStyleSource::Restore, "Restored version {$revision->version}");
    }

    private function clean(?string $body): ?string
    {
        $body = trim((string) $body);

        return $body === '' ? null : Str::limit($body, (int) config('assistant.limits.style_max_chars'), '');
    }
}
