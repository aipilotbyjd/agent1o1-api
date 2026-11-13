<?php

namespace App\Services\Sandbox;

use App\Models\Sandbox\SandboxProvider;
use LogicException;
use RuntimeException;

abstract class AbstractSandboxDriver implements SandboxDriver
{
    public function __construct(protected readonly SandboxProvider $provider) {}

    public function create(int $idleSeconds): array
    {
        throw new LogicException($this->provider->name.' runs code once and keeps no computer.');
    }

    public function run(string $id, ?string $accessToken, string $language, string $code, int $timeoutSeconds): SandboxResult
    {
        throw new LogicException($this->provider->name.' runs code once and keeps no computer.');
    }

    public function readFile(string $id, ?string $accessToken, string $path, int $maxBytes): string
    {
        throw new LogicException($this->provider->name.' keeps no files between runs.');
    }

    public function keepAlive(string $id, int $idleSeconds): void
    {
        throw new LogicException($this->provider->name.' keeps no computer alive.');
    }

    public function destroy(string $id, ?string $accessToken): void
    {
        throw new LogicException($this->provider->name.' keeps no computer to delete.');
    }

    public function check(): void
    {
        $result = $this->runOnce('python', 'print(1 + 1)', 30);

        if ($result->error !== null || trim($result->stdout) !== '2') {
            throw new RuntimeException('The test program did not give the expected answer'.($result->error !== null ? ': '.$result->error : '.'));
        }
    }
}
