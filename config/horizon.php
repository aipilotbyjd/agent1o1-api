<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        // A live screen is waiting on these: a backlog is noticed within seconds.
        'redis:broadcasts' => 5,
        'redis:default' => 60,
        // Webhook-fired, latency-sensitive: alert fast if it backs up.
        'redis:triggers-event' => 10,
        // Bulk/periodic: tolerates more lag before it's worth paging anyone.
        'redis:triggers-poll' => 120,
        // Traversal work: quick, but a backlog here stalls every run behind it.
        'redis:workflows-dispatch' => 30,
        // Node execution: the actual work; can legitimately take longer per job.
        'redis:workflows-execute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        // Drains `broadcasts` first: the Reverb deliveries of `BroadcastsReliably`
        // events, so a live screen never waits behind mail or housekeeping.
        // Also drains `system-notification` and `system-maintenance`: every
        // notification in the app (`WorkspaceEventNotification` for tenants,
        // `AdminAlertNotification` for operators) and the housekeeping jobs
        // (`ExpireStaleWaitsJob`, `RefreshConnectorCredentialJob`) are queued
        // there, and without a supervisor consuming them those jobs would sit
        // unprocessed forever.
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['broadcasts', 'default', 'system-notification', 'system-maintenance'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],

        // FireTriggerEvent: split from polling so a burst of bulk/periodic
        // polls can never delay a webhook-fired event queued behind it.
        'supervisor-triggers-event' => [
            'connection' => 'redis',
            'queue' => ['triggers-event'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 120,
            'nice' => 0,
        ],

        // PollTrigger: bulk/periodic, tolerates more lag than the event queue.
        'supervisor-triggers-poll' => [
            'connection' => 'redis',
            'queue' => ['triggers-poll'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],

        // DispatchNextNodesJob: pure traversal (no external calls), isolated
        // from ExecuteNodeJob so a burst of node completions can't starve
        // the work that figures out what runs next, or vice versa.
        'supervisor-workflows-dispatch' => [
            'connection' => 'redis',
            'queue' => ['workflows-dispatch'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 30,
            'nice' => 0,
        ],

        // ExecuteNodeJob: the actual node work (HTTP calls, AI prompts, ...).
        'supervisor-workflows-execute' => [
            'connection' => 'redis',
            'queue' => ['workflows-execute'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 120,
            'nice' => 0,
        ],

        // FireTriggerEvent for agent-targeted triggers: blocks on a model
        // call, so it's isolated from every other queue and its maxProcesses
        // doubles as a concurrency ceiling against provider rate limits
        // (docs/STRUCTURE.md's "Queues & Horizon" table). `timeout` must stay
        // at or above config('triggers.agent_fire_timeout_seconds').
        'supervisor-ai-agent' => [
            'connection' => 'redis',
            'queue' => ['ai-agent'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 320,
            'nice' => 0,
        ],
        // Personal-assistant turns (`RunAssistantTurnJob`). Long-running by
        // design (up to config('assistant.runtime.max_steps') model steps),
        // so on its own connection whose retry_after exceeds this timeout.
        'supervisor-ai-assistant' => [
            'connection' => 'redis-assistant',
            'queue' => ['ai-assistant'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 1200,
            'nice' => 0,
        ],
        // Subagents started by `InvokeAgentTool`. Separate from `ai-agent` so a
        // parent waiting on its subagents never occupies the workers they need
        // (subagents can't start subagents, so nothing here waits on this
        // queue), and sized for parallel work: `InvokeAgentTool::MAX_CONCURRENT`
        // caps how many one conversation can start.
        'supervisor-ai-subagent' => [
            'connection' => 'redis',
            'queue' => ['ai-subagent'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 320,
            'nice' => 0,
        ],
        // ProcessWorkflowBuilderMessageJob: one builder chat turn, which can
        // run many tool calls against the draft. Kept off `ai-agent` so a
        // burst of people building workflows can't hold up agent-targeted
        // triggers (or the other way round). `timeout` must stay above the
        // job's own $timeout.
        'supervisor-workflow-builder' => [
            'connection' => 'redis',
            'queue' => ['workflow-builder'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 320,
            'nice' => 0,
        ],
        // RecordRunCreditUsage: money-adjacent, so it runs on its own
        // supervisor rather than queuing behind workflow load
        // (docs/STRUCTURE.md's "Queues & Horizon" table). Raising its OS
        // priority with a negative `nice` needs Horizon to run as root, so
        // it's opt-in via HORIZON_BILLING_NICE.
        // `tries` is left to the listener's own $tries/$backoff, which retry a
        // failed charge without double-billing (charges are idempotent per
        // node run).
        'supervisor-billing' => [
            'connection' => 'redis',
            'queue' => ['billing-webhook'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => (int) env('HORIZON_BILLING_NICE', 0),
        ],
        // Super Admin email campaigns: bulk and rate-limited, so they get their
        // own supervisor and can never queue ahead of notifications or billing.
        'supervisor-broadcasts' => [
            'connection' => 'redis',
            'queue' => ['system-broadcast'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-triggers-event' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-triggers-poll' => [
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-workflows-dispatch' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-workflows-execute' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-ai-agent' => [
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-ai-subagent' => [
                'maxProcesses' => 10,
            ],
            'supervisor-ai-assistant' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-workflow-builder' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-billing' => [
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-broadcasts' => [
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'supervisor-triggers-event' => [
                'maxProcesses' => 2,
            ],
            'supervisor-triggers-poll' => [
                'maxProcesses' => 1,
            ],
            'supervisor-workflows-dispatch' => [
                'maxProcesses' => 2,
            ],
            'supervisor-workflows-execute' => [
                'maxProcesses' => 3,
            ],
            'supervisor-ai-agent' => [
                'maxProcesses' => 1,
            ],
            'supervisor-ai-subagent' => [
                'maxProcesses' => 5,
            ],
            'supervisor-ai-assistant' => [
                'maxProcesses' => 2,
            ],
            'supervisor-workflow-builder' => [
                'maxProcesses' => 1,
            ],
            'supervisor-billing' => [
                'maxProcesses' => 1,
            ],
            'supervisor-broadcasts' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
