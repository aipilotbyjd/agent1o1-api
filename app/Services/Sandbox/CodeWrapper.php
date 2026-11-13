<?php

namespace App\Services\Sandbox;

/**
 * Gets a workflow's data into a script and the answer back out, without
 * ever building code from the data: the input travels as base64 text, so
 * nothing in it can end the string it sits in.
 *
 * A script reads `INPUT` (Python, JS) or `$INPUT_JSON` (bash), and ends by
 * printing its answer as JSON on the last line of standard output.
 */
final class CodeWrapper
{
    /**
     * @param  array<string, mixed>  $input
     */
    public static function withInput(string $language, string $code, array $input): string
    {
        $payload = base64_encode(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}');

        return match ($language) {
            'js' => "const INPUT = JSON.parse(Buffer.from('{$payload}', 'base64').toString('utf8'));\n{$code}",
            'bash' => "INPUT_JSON=\$(printf %s '{$payload}' | base64 -d)\nexport INPUT_JSON\n{$code}",
            default => "import base64 as _b64, json as _json\nINPUT = _json.loads(_b64.b64decode('{$payload}').decode('utf-8'))\n{$code}",
        };
    }

    /**
     * The JSON on the last non-empty line of `$stdout`, or null when the
     * script printed none.
     */
    public static function answer(string $stdout): mixed
    {
        $lines = preg_split('/\r?\n/', trim($stdout)) ?: [];
        $last = trim((string) end($lines));

        if ($last === '') {
            return null;
        }

        $decoded = json_decode($last, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
