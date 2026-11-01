<?php

namespace App\Ai\Tools;

use App\Models\Agents\Agent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The delete side of `RememberTool`: without it a fact the user corrects or
 * withdraws stays in every future prompt. Only reaches memories saved in the
 * same scope `RememberTool` writes to (this user's, or workspace-wide when
 * there is no user), so one person can't make the agent forget what it
 * remembers for someone else.
 */
class ForgetTool implements Tool
{
    public const NAME = 'forget';

    public function __construct(
        private readonly Agent $agent,
        private readonly ?string $userId = null,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): Stringable|string
    {
        return 'Deletes a fact you remembered earlier, by its key. '
            .'Use it when the user says something you remember is wrong or out of date and gives no replacement, or asks you to forget it. '
            .'To change a fact, save the new value with `'.RememberTool::NAME.'` under the same key instead.';
    }

    public function handle(Request $request): Stringable|string
    {
        $key = (string) $request['key'];

        $deleted = $this->agent->memories()
            ->where('user_id', $this->userId)
            ->where('key', $key)
            ->delete();

        return $deleted > 0 ? "Forgot {$key}." : "Nothing is remembered under {$key}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required(),
        ];
    }
}
