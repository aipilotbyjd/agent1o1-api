<?php

namespace App\Engine\Execution;

use Illuminate\Support\Facades\Storage;

class OutputBuffer
{
    private const SPILL_THRESHOLD_BYTES = 262144; // 256 KB

    private array $outputs = [];

    private array $refCounts = [];

    private array $spilledFiles = [];

    private string $executionId;

    public function __construct(string $executionId, array $downstreamConsumers = [])
    {
        $this->executionId = $executionId;
        $this->refCounts = $downstreamConsumers;
    }

    public function store(string $nodeId, ?array $output): void
    {
        if ($output === null) {
            $this->outputs[$nodeId] = null;

            return;
        }

        $encoded = json_encode($output);

        if (strlen($encoded) > self::SPILL_THRESHOLD_BYTES) {
            $path = "engine-outputs/{$this->executionId}/{$nodeId}.json";
            Storage::put($path, $encoded);
            $this->spilledFiles[$nodeId] = $path;
            unset($this->outputs[$nodeId]);
        } else {
            $this->outputs[$nodeId] = $output;
        }
    }

    public function get(string $nodeId): ?array
    {
        if (isset($this->spilledFiles[$nodeId])) {
            $contents = Storage::get($this->spilledFiles[$nodeId]);

            return $contents ? json_decode($contents, true) : null;
        }

        return $this->outputs[$nodeId] ?? null;
    }

    public function has(string $nodeId): bool
    {
        return isset($this->outputs[$nodeId]) || isset($this->spilledFiles[$nodeId]);
    }

    public function release(string $nodeId): void
    {
        if (! isset($this->refCounts[$nodeId])) {
            return;
        }

        $this->refCounts[$nodeId]--;

        if ($this->refCounts[$nodeId] <= 0) {
            unset($this->outputs[$nodeId]);

            if (isset($this->spilledFiles[$nodeId])) {
                Storage::delete($this->spilledFiles[$nodeId]);
                unset($this->spilledFiles[$nodeId]);
            }
        }
    }

    public function cleanup(): void
    {
        $dir = "engine-outputs/{$this->executionId}";

        if (Storage::exists($dir)) {
            Storage::deleteDirectory($dir);
        }

        $this->outputs = [];
        $this->spilledFiles = [];
    }

    public function snapshot(): array
    {
        $outputs = $this->outputs;

        foreach ($this->spilledFiles as $nodeId => $path) {
            $contents = Storage::get($path);
            $outputs[$nodeId] = $contents ? json_decode($contents, true) : null;
        }

        return [
            'outputs' => $outputs,
            'ref_counts' => $this->refCounts,
        ];
    }

    public static function fromSnapshot(string $executionId, array $snapshot): self
    {
        $buffer = new self($executionId, $snapshot['ref_counts'] ?? []);
        $buffer->outputs = $snapshot['outputs'] ?? [];

        return $buffer;
    }
}
