<?php

namespace App\Services\Assistant\Computer;

/**
 * A managed cloud sandbox provider. One sandbox per conversation; the
 * provider deletes it after it sits idle.
 */
interface SandboxDriver
{
    /**
     * @return array{id: string, access_token: string|null}
     */
    public function create(int $idleSeconds): array;

    /**
     * @throws SandboxGoneException when the sandbox no longer exists
     */
    public function run(string $id, ?string $accessToken, string $language, string $code, int $timeoutSeconds): SandboxResult;

    /**
     * @throws SandboxGoneException
     */
    public function readFile(string $id, ?string $accessToken, string $path, int $maxBytes): string;

    public function keepAlive(string $id, int $idleSeconds): void;
}
