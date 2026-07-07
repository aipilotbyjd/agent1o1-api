<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Per-node retry policy
    |--------------------------------------------------------------------------
    |
    | Default retry behaviour applied to every node the engine executes. A node
    | may override any of these keys via its own config under a `retry` block,
    | e.g. "config": { "retry": { "max_attempts": 3, "backoff": 2 } }.
    |
    | max_attempts : total handler invocations, including the first (1 = no retry).
    | backoff      : base delay in seconds before the first retry.
    | multiplier   : exponential growth factor applied per subsequent retry.
    | max_backoff  : ceiling in seconds for a single backoff delay.
    |
    | The default of 1 attempt preserves fail-fast behaviour; opt individual
    | nodes (or the global default) into retries as needed.
    |
    */

    'node_retry' => [
        'max_attempts' => (int) env('ENGINE_NODE_MAX_ATTEMPTS', 1),
        'backoff' => (int) env('ENGINE_NODE_BACKOFF', 2),
        'multiplier' => (float) env('ENGINE_NODE_BACKOFF_MULTIPLIER', 2),
        'max_backoff' => (int) env('ENGINE_NODE_MAX_BACKOFF', 60),
    ],

];
