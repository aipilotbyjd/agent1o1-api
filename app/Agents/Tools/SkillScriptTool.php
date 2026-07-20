<?php

namespace App\Agents\Tools;

use App\Models\AgentSkillScript;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Wraps an AgentSkillScript as a callable Laravel AI tool.
 * Supported languages: php, javascript (via node CLI).
 */
class SkillScriptTool implements Tool
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly AgentSkillScript $script,
    ) {}

    public function description(): Stringable|string
    {
        return $this->script->description;
    }

    public function handle(Request $request): Stringable|string
    {
        $input = $request['input'] ?? '';

        try {
            return match ($this->script->language) {
                'php' => $this->runPhp($input),
                'javascript' => $this->runJavaScript($input),
                default => json_encode(['error' => "Unsupported language: {$this->script->language}"]),
            };
        } catch (ProcessTimedOutException) {
            return json_encode(['error' => 'Script timed out after '.self::TIMEOUT_SECONDS.'s']);
        } catch (\Throwable $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'input' => $schema->string(),
        ];
    }

    private function runPhp(string $input): string
    {
        $code = $this->script->code;
        $tmpFile = tempnam(sys_get_temp_dir(), 'skill_').'.php';

        $wrapper = "<?php\n\$input = json_decode(<<<'INPUT'\n{$input}\nINPUT, true);\n\n{$code}";
        file_put_contents($tmpFile, $wrapper);

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run(['php', $tmpFile]);

            return $result->output() ?: $result->errorOutput();
        } finally {
            @unlink($tmpFile);
        }
    }

    private function runJavaScript(string $input): string
    {
        $code = $this->script->code;
        $tmpFile = tempnam(sys_get_temp_dir(), 'skill_').'.js';

        $wrapper = "const input = JSON.parse(`{$input}`);\n\n{$code}";
        file_put_contents($tmpFile, $wrapper);

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run(['node', $tmpFile]);

            return $result->output() ?: $result->errorOutput();
        } finally {
            @unlink($tmpFile);
        }
    }
}
