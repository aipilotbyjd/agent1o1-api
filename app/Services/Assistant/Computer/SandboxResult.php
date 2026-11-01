<?php

namespace App\Services\Assistant\Computer;

/**
 * What one run printed and returned.
 */
final readonly class SandboxResult
{
    /**
     * @param  list<string>  $results  rich results (the value of the last expression, charts as text)
     */
    public function __construct(
        public string $stdout,
        public string $stderr,
        public array $results,
        public ?string $error,
        public int $seconds,
    ) {}
}
