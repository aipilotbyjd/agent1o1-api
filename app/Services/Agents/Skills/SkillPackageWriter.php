<?php

namespace App\Services\Agents\Skills;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillReference;
use App\Models\Agents\SkillScript;
use RuntimeException;

/** Serializes only repository-backed fields; app appearance and access stay local. */
class SkillPackageWriter
{
    public function fromSkill(Skill $skill): SkillPackage
    {
        return new SkillPackage(
            $skill->source_path,
            $skill->name,
            $skill->description,
            $skill->instructions,
            $skill->references->map(fn (SkillReference $reference): array => $reference->only(['title', 'content']))->all(),
            $skill->scripts->map(fn (SkillScript $script): array => $script->only(['name', 'description', 'language', 'code']))->all(),
        );
    }

    /** @return array<string, string> */
    public function files(SkillPackage $package): array
    {
        if (count($package->references) > SkillPackageParser::MAX_FILES_PER_SKILL || count($package->scripts) > SkillPackageParser::MAX_FILES_PER_SKILL) {
            throw new RuntimeException('A synced skill supports at most 30 references and 30 scripts.');
        }

        $files = ['SKILL.md' => "---\nname: ".$this->scalar($package->name)."\ndescription: ".$this->scalar($package->description ?? '')."\n---\n".trim($package->instructions)."\n"];

        foreach ($package->references as $reference) {
            $path = $reference['title'];
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'markdown', 'txt', 'json', 'yaml', 'yml', 'csv'], true)) {
                $path .= '.md';
            }
            $this->add($files, $path, $reference['content']);
        }

        foreach ($package->scripts as $script) {
            $extensions = match ($script['language']) {
                'python' => ['py'],
                'javascript' => ['js', 'mjs', 'cjs'],
                'typescript' => ['ts'],
                'bash' => ['sh', 'bash'],
                default => throw new RuntimeException('This script language cannot be synced to a skill repository.'),
            };
            $path = $script['name'];
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
                $path .= '.'.$extensions[0];
            }
            $this->add($files, $path, $script['code']);
        }

        foreach ($files as $content) {
            if (strlen($content) > GitHubSkillRepository::MAX_FILE_BYTES || ! mb_check_encoding($content, 'UTF-8') || trim($content) === '') {
                throw new RuntimeException('Synced files must contain UTF-8 text and be at most 256 KB.');
            }
        }

        ksort($files);

        return $files;
    }

    public function validatePath(string $path): void
    {
        if ($path === '' || strlen($path) > 255 || preg_match('#(^/|\\\\|[\x00-\x1f\x7f]|(^|/)\.{1,2}(/|$)|//|/$)#', $path)) {
            throw new RuntimeException('Use a relative repository path without dot segments or backslashes.');
        }
    }

    /** Preserve repository-specific frontmatter when rewriting instructions. */
    public function markdown(string $canonical, ?string $original): string
    {
        if ($original === null || ! preg_match('/\A---\n(.*?)\n---[^\n]*(?:\n|\z)/s', str_replace("\r\n", "\n", ltrim($original, "\u{FEFF}")), $match)) {
            return $canonical;
        }

        $metadata = preg_replace('/^(?:name|description):[^\n]*(?:\n(?:[ \t]+[^\n]*|(?=\n)))*/m', '', $match[1]);
        if (trim($metadata) === '') {
            return $canonical;
        }

        return preg_replace_callback('/\n---\n/', fn (): string => "\n".trim($metadata)."\n---\n", $canonical, 1);
    }

    /** @param array<string, string> $files */
    private function add(array &$files, string $path, string $content): void
    {
        $this->validatePath($path);
        if (basename($path) === 'SKILL.md' || isset($files[$path])) {
            throw new RuntimeException('Skill files must have unique paths and cannot replace SKILL.md.');
        }
        $files[$path] = $content;
    }

    private function scalar(string $value): string
    {
        return json_encode(trim($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
