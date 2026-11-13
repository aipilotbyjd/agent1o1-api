<?php

namespace App\Services\Sandbox;

/**
 * One remote provider of code sandboxes, built for a single
 * `SandboxProvider` row. Nothing behind this interface may run code on the
 * app server: every method calls the provider over the network.
 *
 * Two ways to run code. A persistent computer lasts for a conversation
 * (`create`, `run`, `readFile`, `keepAlive`, `destroy`); a one-shot run
 * starts a fresh isolated environment, runs once and discards it
 * (`runOnce`). A kind that cannot do the first (see
 * `SandboxDriverType::supports()`) is never asked to.
 */
interface SandboxDriver
{
    /**
     * @return array{id: string, access_token: string|null}
     *
     * @throws SandboxStartException when the provider could not start a computer
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

    /**
     * Deletes the computer. A computer that is already gone is not an error.
     */
    public function destroy(string $id, ?string $accessToken): void;

    /**
     * Runs `$code` once on a fresh environment that is deleted afterwards.
     *
     * @throws SandboxStartException when nothing was started and the code never ran, so another provider may be tried
     */
    public function runOnce(string $language, string $code, int $timeoutSeconds): SandboxResult;

    /**
     * Proves the provider works by running a tiny program.
     *
     * @throws \RuntimeException with a message an operator can act on
     */
    public function check(): void;
}
