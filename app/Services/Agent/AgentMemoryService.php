<?php

namespace App\Services\Agent;

use App\Agents\Internal\MemoryExtractionAgent;
use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\AgentRun;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Long-horizon memory (roadmap item 4).
 *
 * Two upgrades over the flat key/value store:
 *  - semantic recall: embed memories and pull only the top-K relevant ones into
 *    context for a given message, instead of dumping every row;
 *  - automatic extraction: after a run, propose durable memories from the
 *    exchange and persist them (embedding them for future recall).
 */
class AgentMemoryService
{
    /**
     * Recall the memories most relevant to the current message.
     *
     * Falls back to the newest memories (and never throws) so a flaky embedding
     * provider degrades gracefully rather than breaking the run.
     *
     * @return Collection<int, AgentMemory>
     */
    public function recall(Agent $agent, string $message, ?int $userId, int $limit = 6): Collection
    {
        $memories = $agent->memories
            ->filter(fn (AgentMemory $m) => $m->user_id === null || $m->user_id === $userId)
            ->values();

        if ($memories->count() <= $limit) {
            return $memories;
        }

        $embedded = $memories->filter(fn (AgentMemory $m) => is_array($m->embedding) && $m->embedding !== []);

        if ($embedded->isEmpty()) {
            return $memories->sortByDesc('created_at')->take($limit)->values();
        }

        try {
            $queryVector = Embeddings::for([$message])->generate()->first();
        } catch (Throwable) {
            return $memories->sortByDesc('created_at')->take($limit)->values();
        }

        return $embedded
            ->map(fn (AgentMemory $m) => [
                'memory' => $m,
                'score' => $this->cosineSimilarity($queryVector, $m->embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('memory')
            ->values();
    }

    /**
     * Read the exchange, propose durable memories, and persist the new ones.
     * De-dupes against existing keys for the same scope. Best-effort: any failure
     * is swallowed so it never breaks the surrounding run.
     *
     * @return int Number of memories stored.
     */
    public function extractAndStore(Agent $agent, string $userMessage, string $assistantReply, ?int $userId, ?AgentRun $run = null): int
    {
        try {
            $response = (new MemoryExtractionAgent)->prompt(
                "User:\n{$userMessage}\n\nAssistant:\n{$assistantReply}",
                provider: $agent->provider,
                model: $agent->model,
            );

            $proposed = $response['memories'] ?? [];
        } catch (Throwable) {
            return 0;
        }

        if (! is_iterable($proposed)) {
            return 0;
        }

        $existingKeys = $agent->memories()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
            ->pluck('key')
            ->map(fn ($k) => strtolower($k))
            ->all();

        $stored = 0;

        foreach ($proposed as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));

            if ($key === '' || $value === '' || in_array(strtolower($key), $existingKeys, true)) {
                continue;
            }

            $memory = new AgentMemory([
                'agent_id' => $agent->id,
                'workspace_id' => $agent->workspace_id,
                'user_id' => $userId,
                'agent_run_id' => $run?->id,
                'key' => $key,
                'value' => $value,
                'type' => $item['type'] ?? 'fact',
                'source' => 'auto',
            ]);
            $memory->embedding = $this->embed("{$key}: {$value}");
            $memory->save();

            $existingKeys[] = strtolower($key);
            $stored++;
        }

        return $stored;
    }

    /**
     * Compute and persist an embedding for a memory that is missing one.
     */
    public function backfillEmbedding(AgentMemory $memory): void
    {
        if (is_array($memory->embedding) && $memory->embedding !== []) {
            return;
        }

        $vector = $this->embed("{$memory->key}: {$memory->value}");

        if ($vector !== null) {
            $memory->forceFill(['embedding' => $vector])->save();
        }
    }

    /**
     * @return array<int, float>|null
     */
    private function embed(string $text): ?array
    {
        try {
            return Embeddings::for([$text])->generate()->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valA) {
            $valB = $b[$i] ?? 0.0;
            $dot += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
