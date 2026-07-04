<?php

namespace App\Agents\Tools;

use App\Models\AgentSkillScript;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Wraps an AgentSkillScript as a callable Laravel AI tool.
 * Supported languages: php, javascript (via node CLI).
 */
class SkillScriptTool implements Tool
{
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

        $output = shell_exec('php '.escapeshellarg($tmpFile).' 2>&1');
        @unlink($tmpFile);

        return $output ?? '';
    }

    private function runJavaScript(string $input): string
    {
        $code = $this->script->code;
        $tmpFile = tempnam(sys_get_temp_dir(), 'skill_').'.js';

        $wrapper = "const input = JSON.parse(`{$input}`);\n\n{$code}";
        file_put_contents($tmpFile, $wrapper);

        $output = shell_exec('node '.escapeshellarg($tmpFile).' 2>&1');
        @unlink($tmpFile);

        return $output ?? '';
    }
}
