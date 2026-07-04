<?php

namespace App\Engine\Nodes\Apps\Rag;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class ChunkerNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        $text = (string) ($input->config['text'] ?? '');
        $chunkSize = max(100, (int) ($input->config['chunk_size'] ?? 1000));
        $overlap = min((int) ($input->config['overlap'] ?? 200), $chunkSize - 1);
        $strategy = $input->config['strategy'] ?? 'characters'; // characters | sentences | paragraphs

        if ($text === '') {
            return $this->success(['chunks' => [], 'count' => 0]);
        }

        $chunks = match ($strategy) {
            'paragraphs' => $this->chunkByParagraphs($text, $chunkSize),
            'sentences' => $this->chunkBySentences($text, $chunkSize),
            default => $this->chunkByCharacters($text, $chunkSize, $overlap),
        };

        return $this->success(['chunks' => $chunks, 'count' => count($chunks)]);
    }

    private function chunkByCharacters(string $text, int $size, int $overlap): array
    {
        $chunks = [];
        $step = $size - $overlap;
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset += $step) {
            $chunks[] = mb_substr($text, $offset, $size);

            if ($offset + $size >= $length) {
                break;
            }
        }

        return $chunks;
    }

    private function chunkBySentences(string $text, int $maxSize): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];

        return $this->packPieces($sentences, $maxSize, ' ');
    }

    private function chunkByParagraphs(string $text, int $maxSize): array
    {
        $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];

        return $this->packPieces($paragraphs, $maxSize, "\n\n");
    }

    private function packPieces(array $pieces, int $maxSize, string $glue): array
    {
        $chunks = [];
        $current = '';

        foreach ($pieces as $piece) {
            $candidate = $current === '' ? $piece : $current.$glue.$piece;

            if (mb_strlen($candidate) > $maxSize && $current !== '') {
                $chunks[] = $current;
                $current = $piece;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
