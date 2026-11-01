<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An agent turn runs inside the request that sent the message, and one that
 * starts subagents waits on them (`WaitForSubagentsTool`) before writing its
 * reply: well past PHP's default 30-second `max_execution_time`. Hitting
 * that limit is a fatal error nothing can catch: the reply is lost, the
 * subagents' results were already collected, and the turn's `Run` is left
 * `running` for good.
 *
 * The limit is raised, not removed, so a hung provider call still ends. It
 * allows for a subagent running its full `RunSubagentTaskJob::$timeout`
 * (300s) and the turn around it.
 */
class AllowLongAgentTurn
{
    public const SECONDS = 600;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        set_time_limit(self::SECONDS);

        return $next($request);
    }
}
