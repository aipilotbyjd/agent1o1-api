<?php

namespace App\Services\Agents\Skills;

/**
 * One skill read from a repository folder: see `SkillPackageParser`.
 */
final readonly class SkillPackage
{
    /**
     * @param  string  $path  the folder, from the repository root ('' for the root itself)
     * @param  list<array{title: string, content: string}>  $references
     * @param  list<array{name: string, description: null, language: string, code: string}>  $scripts
     */
    public function __construct(
        public string $path,
        public string $name,
        public ?string $description,
        public string $instructions,
        public array $references,
        public array $scripts,
    ) {}
}
