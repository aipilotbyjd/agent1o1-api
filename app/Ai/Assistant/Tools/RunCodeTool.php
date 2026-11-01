<?php

namespace App\Ai\Assistant\Tools;

use App\Enums\Assistant\AssistantToolEffect;
use App\Models\Assistant\AssistantSession;
use App\Services\Assistant\Computer\Sandboxes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Runs code on the conversation's own cloud computer. It is isolated from
 * the platform and holds no credentials (only what the model puts there)
 * so it counts as the assistant's own workspace (`Internal`); the owner can
 * still make it ask first.
 */
class RunCodeTool extends AssistantTool
{
    public const string NAME = 'run_code';

    private const array LANGUAGES = ['python', 'js', 'bash'];

    public function __construct(
        private readonly AssistantSession $session,
        private readonly Sandboxes $sandboxes,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function effect(): AssistantToolEffect
    {
        return AssistantToolEffect::Internal;
    }

    public function description(): Stringable|string
    {
        return 'Runs code on your own cloud computer for this conversation (Linux, internet access, Python with pandas/numpy/matplotlib preinstalled). '
            .'Use it for calculations, data analysis, charts, converting files or anything easier in code. '
            .'Files you write (under /home/user) stay for the rest of the conversation while it is in use; hand one to the person with export_file and its sandbox_path. '
            .'Python keeps variables between runs. Print what you need to see.';
    }

    protected function execute(Request $request): string
    {
        $language = in_array($request['language'] ?? 'python', self::LANGUAGES, true) ? (string) ($request['language'] ?? 'python') : 'python';

        [$result, $fresh] = $this->sandboxes->run($this->session, $language, (string) $request['code']);
        $max = (int) config('assistant.sandbox.max_output_chars');

        return json_encode(array_filter([
            'note' => $fresh ? 'This was a fresh computer: files and variables from earlier runs are gone.' : null,
            'stdout' => Str::limit($result->stdout, $max, "\n…(output cut)"),
            'stderr' => Str::limit($result->stderr, intdiv($max, 4), "\n…(output cut)"),
            'results' => array_map(fn (string $value): string => Str::limit($value, intdiv($max, 4)), $result->results),
            'error' => $result->error,
            'seconds' => $result->seconds,
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->required(),
            'language' => $schema->string()->enum(self::LANGUAGES)->description('Defaults to python.'),
        ];
    }
}
