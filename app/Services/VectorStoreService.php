<?php

namespace App\Services;

use App\Models\DocumentEmbedding;
use App\Models\Workspace;
use Laravel\Ai\Embeddings;

class VectorStoreService
{
    private const CHUNK_SIZE = 1000;

    /**
     * Chunk, embed, and store a document in the workspace vector store.
     *
     * @param  array<string, mixed>  $metadata
     * @return int Number of chunks stored.
     */
    public function ingest(Workspace $workspace, string $collection, string $text, ?string $source = null, array $metadata = []): int
    {
        $chunks = $this->chunk($text);

        if ($chunks === []) {
            return 0;
        }

        $vectors = Embeddings::for($chunks)->generate()->embeddings;

        foreach ($chunks as $i => $chunk) {
            if (! isset($vectors[$i])) {
                continue;
            }

            DocumentEmbedding::create([
                'workspace_id' => $workspace->id,
                'collection' => $collection,
                'source' => $source,
                'chunk_text' => $chunk,
                'embedding' => $vectors[$i],
                'metadata' => $metadata === [] ? null : $metadata,
            ]);
        }

        return count($chunks);
    }

    /**
     * Semantic search over a workspace collection.
     *
     * @return array<int, array{id: string, text: string, score: float, source: ?string}>
     */
    public function query(Workspace $workspace, string $collection, string $query, int $limit = 5): array
    {
        $queryVector = Embeddings::for([$query])->generate()->first();

        return DocumentEmbedding::query()
            ->where('workspace_id', $workspace->id)
            ->where('collection', $collection)
            ->get()
            ->map(fn (DocumentEmbedding $doc) => [
                'id' => $doc->id,
                'text' => $doc->chunk_text,
                'source' => $doc->source,
                'score' => self::cosineSimilarity($queryVector, $doc->embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        return collect(str_split($text, self::CHUNK_SIZE))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
