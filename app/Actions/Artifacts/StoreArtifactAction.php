<?php

namespace App\Actions\Artifacts;

use App\Enums\Artifacts\ArtifactGeneralAccess;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Artifacts\Artifact;
use App\Models\Assistant\AssistantSession;
use App\Models\Runs\Run;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\KnowledgeBase;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Writes one artifact: the single place bytes land on disk and a row is
 * written, shared by the Internal API's upload endpoint and
 * `App\Ai\Tools\ExportArtifactTool` so "what does storing an artifact
 * actually do" (versioning, path layout, filename safety) has one
 * implementation.
 *
 * Versioning: a store never overwrites. It appends a new version to the
 * matching group: the group named by `$groupId`, or failing that the group
 * of the newest artifact with the same filename in the same scope (the
 * agent session for an agent export, the workspace's uploads for an upload),
 * and starts a fresh group when there's nothing to match.
 *
 * When the artifact is filed under an agent and its content is
 * text-extractable, it is also indexed into that agent's
 * `Agent::artifactKnowledgeCollection()`: Gumloop's "Your agents'
 * artifacts" (docs/gumloop/output/raw/core-concepts/brain.md), letting the
 * agent search its own past outputs. Only the newest version stays
 * indexed, mirroring the doc's "updates in place" behavior.
 */
class StoreArtifactAction
{
    /**
     * Mime types read as UTF-8 text for indexing. Narrower than
     * `config('knowledge_base.allowed_extensions')`: this only decides
     * whether an *already-stored* artifact's bytes are safe to treat as
     * text, not what a knowledge-base upload accepts.
     *
     * @var array<int, string>
     */
    private const INDEXABLE_MIME_TYPES = [
        'text/plain', 'text/markdown', 'text/html', 'text/csv',
        'application/json', 'application/xml', 'text/xml', 'text/yaml', 'application/yaml',
    ];

    /** How often a store re-reads the latest version after losing a race for it. */
    private const int VERSION_ATTEMPTS = 5;

    public function __construct(
        private readonly KnowledgeBase $knowledgeBase,
    ) {}

    /**
     * `$searchable: false` skips indexing into the agent's knowledge
     * collection, for chat message attachments, which belong to one
     * member's conversation and must not become searchable from anyone
     * else's.
     *
     * @param  string|UploadedFile  $contents  Raw bytes, or the uploaded file to stream to disk.
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        Workspace $workspace,
        string $filename,
        string $mimeType,
        string|UploadedFile $contents,
        ?Agent $agent = null,
        ?AgentSession $session = null,
        ?Run $run = null,
        ?string $createdBy = null,
        ?string $groupId = null,
        ?array $metadata = null,
        ?AgentMessage $message = null,
        bool $searchable = true,
        ?AssistantSession $assistantSession = null,
        ?WorkflowBuilderMessage $builderMessage = null,
    ): Artifact {
        $disk = (string) config('artifacts.disk');

        // The bytes land at a collision-proof temporary path first: the final
        // path is derived from the version, and two concurrent stores can
        // pick the same one. Only the store that wins the version (the
        // unique `(group_id, version)` index) moves its bytes into place.
        $stagingPath = "artifacts/{$workspace->id}/.incoming/".Str::uuid();

        $this->write($disk, $stagingPath, $contents);

        try {
            $artifact = $this->createVersion(
                $workspace, $filename, $mimeType, $contents, $disk, $agent, $session, $run,
                $createdBy, $groupId, $metadata, $message, $assistantSession, $builderMessage,
            );

            if (! Storage::disk($disk)->move($stagingPath, $artifact->path)) {
                $artifact->forceDelete();

                throw new RuntimeException("Could not store artifact [{$filename}].");
            }
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($stagingPath);

            throw $exception;
        }

        if ($searchable && $agent !== null && in_array($mimeType, self::INDEXABLE_MIME_TYPES, true)) {
            // The artifact is already stored; an embeddings outage must not
            // fail the export, it only leaves the file unsearchable.
            try {
                $this->indexForAgent($workspace, $agent, $artifact, $contents);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $artifact;
    }

    /**
     * Appends the next version to the matching group. Concurrent stores can
     * read the same latest version; the unique `(group_id, version)` index
     * rejects the loser, which re-reads and takes the next number.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private function createVersion(
        Workspace $workspace,
        string $filename,
        string $mimeType,
        string|UploadedFile $contents,
        string $disk,
        ?Agent $agent,
        ?AgentSession $session,
        ?Run $run,
        ?string $createdBy,
        ?string $groupId,
        ?array $metadata,
        ?AgentMessage $message,
        ?AssistantSession $assistantSession,
        ?WorkflowBuilderMessage $builderMessage,
    ): Artifact {
        for ($attempt = 1; ; $attempt++) {
            // `withTrashed()`: a soft-deleted group must still be found here, both
            // to keep versioning past its last version number and so a matching
            // re-export restores it: see the soft-delete note on the migration.
            $previous = Artifact::withTrashed()
                ->where('workspace_id', $workspace->id)
                ->when(
                    $groupId !== null,
                    fn ($query) => $query->where('group_id', $groupId),
                    fn ($query) => $query
                        ->where('filename', $filename)
                        ->when(
                            $session !== null,
                            fn ($scoped) => $scoped->where('agent_session_id', $session->id),
                            fn ($scoped) => $scoped->whereNull('agent_session_id'),
                        )
                        ->when(
                            $assistantSession !== null,
                            fn ($scoped) => $scoped->where('assistant_session_id', $assistantSession->id),
                            fn ($scoped) => $scoped->whereNull('assistant_session_id'),
                        ),
                )
                ->orderByDesc('version')
                ->first();

            if ($previous?->trashed()) {
                Artifact::withTrashed()->where('group_id', $previous->group_id)->restore();
            }

            $resolvedGroupId = $previous?->group_id ?? $groupId ?? (string) Str::uuid();
            $version = $previous ? $previous->version + 1 : 1;

            try {
                return Artifact::create([
                    'workspace_id' => $workspace->id,
                    'agent_id' => $agent?->id,
                    'agent_session_id' => $session?->id,
                    'assistant_session_id' => $assistantSession?->id,
                    'agent_message_id' => $message?->id,
                    'workflow_builder_message_id' => $builderMessage?->id,
                    'run_id' => $run?->id,
                    'created_by' => $createdBy,
                    'group_id' => $resolvedGroupId,
                    'version' => $version,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'size' => $contents instanceof UploadedFile ? (int) $contents->getSize() : strlen($contents),
                    'disk' => $disk,
                    'path' => "artifacts/{$workspace->id}/{$resolvedGroupId}/v{$version}-{$this->safeName($filename)}",
                    'metadata' => $metadata,
                    // A new version keeps the group's existing sharing tier rather
                    // than resetting to restricted.
                    'general_access' => $previous?->general_access?->value ?? ArtifactGeneralAccess::Restricted->value,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::VERSION_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    private function indexForAgent(Workspace $workspace, Agent $agent, Artifact $artifact, string|UploadedFile $contents): void
    {
        $text = $contents instanceof UploadedFile
            ? (string) file_get_contents($contents->getRealPath())
            : $contents;

        if (trim($text) === '') {
            return;
        }

        $collection = $agent->artifactKnowledgeCollection();

        // Only the newest version stays indexed: `replaceSource` swaps the
        // previous version's chunks for the new ones in one transaction, so a
        // failed embedding call leaves the old index intact.
        $this->knowledgeBase->ingest(
            $workspace,
            $text,
            $artifact->filename,
            $collection,
            ['artifact_id' => $artifact->id, 'group_id' => $artifact->group_id],
            replaceSource: true,
        );
    }

    private function write(string $disk, string $path, string|UploadedFile $contents): void
    {
        // Streams the temp file rather than pulling a multi-megabyte upload
        // through memory first.
        $written = $contents instanceof UploadedFile
            ? Storage::disk($disk)->putFileAs(dirname($path), $contents, basename($path))
            : Storage::disk($disk)->put($path, $contents);

        if ($written === false) {
            throw new RuntimeException('Could not write the artifact to storage.');
        }
    }

    /**
     * The path segment for a filename. The stored `filename` keeps whatever
     * the uploader (or the model, for an agent export) supplied; the path
     * never does: a name like `../../.env` would otherwise decide where the
     * bytes land.
     */
    private function safeName(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return $extension === '' ? $stem : "{$stem}.".Str::slug($extension);
    }
}
