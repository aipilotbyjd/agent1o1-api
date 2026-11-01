<?php

namespace App\Services\Agents\Skills;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a repository's files into skills, in the Agent Skills layout: every
 * folder holding a `SKILL.md` is one skill. `SKILL.md`'s frontmatter gives
 * its `name` and `description`, its body the instructions. The folder's
 * other text files become references and its code files scripts, each named
 * by its path inside the folder. A folder nested in another skill's folder
 * is its own skill, not part of the outer one.
 */
class SkillPackageParser
{
    /** Most skills one source may hold; folders past it are left out. */
    public const int MAX_SKILLS = 100;

    /** Most references, and most scripts, one skill may carry. */
    public const int MAX_FILES_PER_SKILL = 30;

    private const array REFERENCE_EXTENSIONS = ['md', 'markdown', 'txt', 'json', 'yaml', 'yml', 'csv'];

    private const array SCRIPT_LANGUAGES = [
        'py' => 'python',
        'js' => 'javascript',
        'mjs' => 'javascript',
        'cjs' => 'javascript',
        'ts' => 'typescript',
        'sh' => 'bash',
        'bash' => 'bash',
    ];

    /**
     * @param  array<string, string>  $files  contents keyed by path from the repository root
     * @param  string|null  $root  only folders inside this one are read
     * @return list<SkillPackage>
     */
    public function parse(array $files, ?string $root = null, bool $strict = false): array
    {
        $root = trim((string) $root, '/');

        if ($strict && count(array_filter(array_keys($files), fn (string $path): bool => basename($path) === 'SKILL.md' && $this->within($path, $root))) > self::MAX_SKILLS) {
            throw new RuntimeException('The repository folder contains more than 100 skills.');
        }

        $folders = collect(array_keys($files))
            ->filter(fn (string $path): bool => basename($path) === 'SKILL.md' && $this->within($path, $root))
            ->map(fn (string $path): string => $this->folder($path))
            ->sort()
            ->take(self::MAX_SKILLS)
            ->values();

        return $folders
            ->map(fn (string $folder): ?SkillPackage => $this->package($folder, $files, $folders->all(), $strict))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $files
     * @param  list<string>  $folders  every skill folder, to leave nested skills out
     */
    private function package(string $folder, array $files, array $folders, bool $strict): ?SkillPackage
    {
        [$meta, $body] = $this->frontmatter($files[ltrim("{$folder}/SKILL.md", '/')]);

        $description = trim((string) ($meta['description'] ?? '')) ?: null;
        $instructions = trim($body) ?: (string) $description;

        if ($strict && ($instructions === '' || ! mb_check_encoding($instructions, 'UTF-8'))) {
            throw new RuntimeException('Every synced SKILL.md must contain UTF-8 instructions.');
        }
        if ($instructions === '') {
            return null;
        }

        $nested = array_filter($folders, fn (string $other): bool => $other !== $folder && $this->within($other.'/', $folder));
        $references = [];
        $scripts = [];

        foreach ($files as $path => $content) {
            if (! $this->within($path, $folder) || basename($path) === 'SKILL.md') {
                continue;
            }

            if (array_filter($nested, fn (string $other): bool => $this->within($path, $other)) !== []) {
                continue;
            }

            $relative = ltrim(Str::after($path, $folder === '' ? '' : "{$folder}/"), '/');
            if ($strict && mb_strlen($relative) > 255) {
                throw new RuntimeException('A synced skill file path exceeds 255 characters.');
            }
            $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

            if ($strict && ((isset(self::SCRIPT_LANGUAGES[$extension]) && count($scripts) >= self::MAX_FILES_PER_SKILL) || (in_array($extension, self::REFERENCE_EXTENSIONS, true) && count($references) >= self::MAX_FILES_PER_SKILL))) {
                throw new RuntimeException('A synced skill supports at most 30 references and 30 scripts.');
            }
            if (! mb_check_encoding($content, 'UTF-8') || trim($content) === '') {
                continue;
            }

            if (isset(self::SCRIPT_LANGUAGES[$extension]) && count($scripts) < self::MAX_FILES_PER_SKILL) {
                $scripts[] = ['name' => Str::limit($relative, 255, ''), 'description' => null, 'language' => self::SCRIPT_LANGUAGES[$extension], 'code' => $content];
            } elseif (in_array($extension, self::REFERENCE_EXTENSIONS, true) && count($references) < self::MAX_FILES_PER_SKILL) {
                $references[] = ['title' => Str::limit($relative, 255, ''), 'content' => $content];
            }
        }

        $name = trim((string) ($meta['name'] ?? '')) ?: ($folder === '' ? 'Skill' : basename($folder));

        if ($strict && mb_strlen($name) > 255) {
            throw new RuntimeException('A synced skill name exceeds 255 characters.');
        }

        return new SkillPackage($folder, Str::limit($name, 255, ''), $description, $instructions, $references, $scripts);
    }

    /**
     * Splits `SKILL.md` into its frontmatter's top-level string fields and its
     * body. Reads the YAML a skill's frontmatter uses (`key: value`, quoted
     * values, `|`/`>` block scalars and indented continuation lines) and
     * skips anything nested.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function frontmatter(string $markdown): array
    {
        $markdown = str_replace("\r\n", "\n", ltrim($markdown, "\u{FEFF}"));

        if (! preg_match('/\A---\n(.*?)\n---[ \t]*(?:\n|\z)(.*)\z/s', $markdown, $match)) {
            return [[], $markdown];
        }

        $meta = [];
        $key = null;
        $block = null;
        $lines = [];

        $flush = function () use (&$meta, &$key, &$block, &$lines): void {
            if ($key !== null && $lines !== []) {
                $meta[$key] = trim($block === '|' ? implode("\n", $lines) : implode(' ', array_map(trim(...), $lines)));
            }

            $key = $block = null;
            $lines = [];
        };

        foreach (explode("\n", $match[1]) as $line) {
            if ($key !== null && ($line === '' || preg_match('/^\s/', $line))) {
                $lines[] = $block === '|' ? preg_replace('/^ {1,2}/', '', $line) : $line;

                continue;
            }

            $flush();

            if (! preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $field)) {
                continue;
            }

            $value = trim($field[2]);

            if ($value === '' || preg_match('/^[|>][+-]?$/', $value)) {
                // A block scalar, or a nested map/list: read the indented
                // lines; a nested structure just ends up as unused text.
                $key = $field[1];
                $block = $value === '' ? '>' : $value[0];

                continue;
            }

            $meta[$field[1]] = $this->scalar($value);
        }

        $flush();

        return [$meta, $match[2]];
    }

    private function scalar(string $value): string
    {
        if (preg_match('/^"(.*)"$/s', $value, $quoted)) {
            return stripcslashes($quoted[1]);
        }

        if (preg_match("/^'(.*)'$/s", $value, $quoted)) {
            return str_replace("''", "'", $quoted[1]);
        }

        return trim(preg_replace('/\s+#.*$/', '', $value));
    }

    private function folder(string $skillFile): string
    {
        $folder = dirname($skillFile);

        return $folder === '.' ? '' : $folder;
    }

    private function within(string $path, string $folder): bool
    {
        return $folder === '' || str_starts_with($path, "{$folder}/");
    }
}
