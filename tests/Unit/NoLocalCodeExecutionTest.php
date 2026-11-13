<?php

use App\Services\Sandbox\AbstractSandboxDriver;
use App\Services\Sandbox\SandboxDriver;

/*
| User and AI code must never run on the app server. Code runs only through
| the sandbox providers in `App\Services\Sandbox`, which call remote
| services. This fails the build if application code starts running
| programs or evaluating strings itself.
*/

arch('the app never evaluates strings or runs programs on the server')
    ->expect('App')
    ->not->toUse(['eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec'])
    ->not->toUse(['Symfony\Component\Process\Process', 'Illuminate\Support\Facades\Process']);

arch('sandbox drivers only talk to the network')
    ->expect('App\Services\Sandbox\Drivers')
    ->toImplement(SandboxDriver::class)
    ->toExtend(AbstractSandboxDriver::class);

arch('the old driver namespace is gone')
    ->expect('App\Services\Assistant\Computer')
    ->not->toUse([
        'App\Services\Assistant\Computer\SandboxDriver',
        'App\Services\Assistant\Computer\E2BSandboxDriver',
    ]);
