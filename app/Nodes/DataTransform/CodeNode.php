<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\Billing\Feature;
use App\Enums\NodeCategory;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Sandbox\CodeRunner;
use App\Services\Sandbox\CodeWrapper;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Your own Python or JavaScript, run on a remote isolated sandbox. The code
 * never runs on this server and never sees the platform's secrets: it gets
 * only the data this node is given as `INPUT`, and returns its answer by
 * printing JSON on the last line. Which provider runs it, and what it
 * costs, is managed in the Super Admin panel (see `CodeRunner`).
 *
 * The older `run_code` node stays as it was: a fixed set of safe
 * operations with no code at all.
 */
class CodeNode implements DeclaresEffect, HasIcon, NodeContract
{
    private const array LANGUAGES = ['python' => 'Python', 'js' => 'JavaScript'];

    public function __construct(private readonly CodeRunner $runner) {}

    public function type(): string
    {
        return 'code';
    }

    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    public function name(): string
    {
        return 'Code';
    }

    public function icon(): string
    {
        return 'source-code';
    }

    public function description(): string
    {
        return 'Runs your Python or JavaScript on a remote, isolated sandbox. Read the data as INPUT and print your answer as JSON on the last line.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['language', 'code'],
            'properties' => [
                'language' => Field::select('Language', self::LANGUAGES, null, 'python'),
                'code' => Field::textarea('Code', 'Read the data as INPUT. Print your result as JSON on the last line, for example print(json.dumps({"total": 3})).', "import json\nprint(json.dumps({\"ok\": True}))"),
                'timeout_seconds' => Field::advanced(Field::integer('Time limit (seconds)', 'The run is stopped after this long.', (int) config('sandbox.code.default_timeout_seconds'), 1, (int) config('sandbox.code.max_timeout_seconds'))),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $workspace = $run->workspace;

        if (! $workspace->currentPlan()?->hasFeature(Feature::CodeExecution)) {
            throw new RuntimeException('Running your own code is not part of this workspace\'s plan.');
        }

        $language = (string) ($config['language'] ?? 'python');
        $code = (string) ($config['code'] ?? '');

        if (! array_key_exists($language, self::LANGUAGES)) {
            throw new InvalidArgumentException("Unsupported language [{$language}].");
        }

        if (trim($code) === '') {
            throw new InvalidArgumentException('There is no code to run.');
        }

        if (strlen($code) > (int) config('sandbox.code.max_code_kilobytes') * 1024) {
            throw new InvalidArgumentException('The code is too long.');
        }

        if (strlen((string) json_encode($context)) > (int) config('sandbox.code.max_input_kilobytes') * 1024) {
            throw new InvalidArgumentException('The data passed to the code is too large.');
        }

        $timeout = max(1, min((int) ($config['timeout_seconds'] ?? config('sandbox.code.default_timeout_seconds')), (int) config('sandbox.code.max_timeout_seconds')));

        ['result' => $result] = $this->runner->run($workspace, $language, $code, $context, $timeout);

        if ($result->error !== null) {
            throw new RuntimeException('Your code failed: '.Str::limit($result->error, 1000, '…'));
        }

        $max = (int) config('sandbox.code.max_output_chars');

        return [
            'result' => CodeWrapper::answer($result->stdout),
            'stdout' => Str::limit($result->stdout, $max, "\n…(output cut)"),
            'stderr' => Str::limit($result->stderr, intdiv($max, 4), "\n…(output cut)"),
            'seconds' => $result->seconds,
        ];
    }
}
