<?php

namespace App\Agents\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Code execution sandbox tool (roadmap item 5). A generic "run a short script"
 * tool for data transforms, calculations, and parsing that a single API call
 * can't express. Runs Python or JavaScript in a subprocess with a hard timeout;
 * whatever the script prints to stdout is returned to the agent.
 *
 * NOTE: this is process-level isolation with a timeout — adequate for trusted,
 * first-party use. For untrusted multi-tenant execution, point RUN at a real
 * sandbox service (gVisor/Firecracker/E2B/Piston) by overriding the runner.
 */
class CodeExecutionTool implements Tool
{
    private const TIMEOUT_SECONDS = 15;

    private const MAX_OUTPUT = 12000;

    public function description(): Stringable|string
    {
        return 'Run a short Python or JavaScript script in a sandbox for data '
            .'transforms, math, or parsing that a single API call cannot do. '
            .'Print the result to stdout — only stdout is returned. No network '
            .'or filesystem access should be relied upon.';
    }

    public function handle(Request $request): Stringable|string
    {
        $language = strtolower(trim((string) ($request['language'] ?? 'python')));
        $code = (string) ($request['code'] ?? '');

        if (trim($code) === '') {
            return json_encode(['error' => 'No code provided.']);
        }

        try {
            return match ($language) {
                'python', 'py', 'python3' => $this->run(['python3'], $code, 'py'),
                'javascript', 'js', 'node' => $this->run(['node'], $code, 'js'),
                default => json_encode(['error' => "Unsupported language: {$language}. Use 'python' or 'javascript'."]),
            };
        } catch (ProcessTimedOutException) {
            return json_encode(['error' => 'Execution timed out after '.self::TIMEOUT_SECONDS.'s.']);
        } catch (\Throwable $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->description("'python' or 'javascript'.")->required(),
            'code' => $schema->string()->description('The script to run. Print results to stdout.')->required(),
        ];
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command, string $code, string $ext): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'agent_code_').'.'.$ext;
        file_put_contents($tmpFile, $code);

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run([...$command, $tmpFile]);

            $stdout = $this->truncate($result->output());
            $stderr = $this->truncate($result->errorOutput());

            return json_encode(array_filter([
                'exit_code' => $result->exitCode(),
                'stdout' => $stdout,
                'stderr' => $result->successful() ? null : $stderr,
            ], fn ($v) => $v !== null && $v !== ''));
        } finally {
            @unlink($tmpFile);
        }
    }

    private function truncate(string $output): string
    {
        if (strlen($output) <= self::MAX_OUTPUT) {
            return $output;
        }

        return substr($output, 0, self::MAX_OUTPUT)."\n…[truncated]";
    }
}
