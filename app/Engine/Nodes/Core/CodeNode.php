<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CodeNode implements NodeHandler
{
    private const TIMEOUT = 30;

    public function handle(NodeInput $input): NodeResult
    {
        $language = $input->config['language'] ?? 'javascript';
        $code = $input->config['code'] ?? '';

        if (empty($code)) {
            return NodeResult::completed([]);
        }

        return match ($language) {
            'javascript', 'js' => $this->runJavaScript($code, $input),
            'python' => $this->runPython($code, $input),
            default => NodeResult::failed("Unsupported language: {$language}"),
        };
    }

    private function runJavaScript(string $code, NodeInput $input): NodeResult
    {
        $contextJson = json_encode([
            'input' => $input->inputData,
            'config' => $input->config,
            'variables' => $input->variables,
        ], JSON_THROW_ON_ERROR);

        // Context via stdin prevents user-controlled data values from escaping
        // the JSON literal and becoming executable JavaScript.
        //
        // User code runs inside __run so that `return value` works. Assigning to
        // `output` also still works, since __run closes over it. An explicit
        // return wins over an assignment when both are present.
        $wrapper = <<<'JS'
        let __raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', c => __raw += c);
        process.stdin.on('end', () => {
            const __ctx = JSON.parse(__raw);
            const input = __ctx.input;
            const variables = __ctx.variables;
            let output;
            const __run = (input, variables) => {
                __USER_CODE__
            };
            const __returned = __run(input, variables);
            if (__returned !== undefined) output = __returned;
            process.stdout.write(JSON.stringify(output !== undefined ? output : {}));
        });
        JS;

        $script = str_replace('__USER_CODE__', $code, $wrapper);

        return $this->runProcessWithStdin(
            ['node', '--disallow-code-generation-from-strings', '--eval', $script],
            $contextJson,
        );
    }

    private function runPython(string $code, NodeInput $input): NodeResult
    {
        $contextJson = json_encode([
            'input' => $input->inputData,
            'config' => $input->config,
            'variables' => $input->variables,
        ], JSON_THROW_ON_ERROR);

        // Context via stdin prevents injection via data values containing Python
        // string delimiters (e.g. triple-quotes) that would escape the literal.
        $script = implode("\n", [
            'import json, sys',
            '__ctx = json.loads(sys.stdin.read())',
            "input = __ctx['input']",
            "variables = __ctx['variables']",
            'output = {}',
            $code,
            'print(json.dumps(output))',
        ]);

        return $this->runProcessWithStdin(['python3', '-c', $script], $contextJson);
    }

    private function runProcessWithStdin(array $command, string $stdin): NodeResult
    {
        try {
            $process = new Process($command, timeout: self::TIMEOUT);

            if ($stdin !== '') {
                $process->setInput($stdin);
            }

            $process->run();

            if (! $process->isSuccessful()) {
                return NodeResult::failed("Code execution error: {$process->getErrorOutput()}");
            }

            $output = json_decode($process->getOutput(), true);

            return NodeResult::completed(is_array($output) ? $output : ['result' => $output]);
        } catch (ProcessTimedOutException) {
            return NodeResult::failed('Code execution timed out after '.self::TIMEOUT.'s');
        }
    }
}
