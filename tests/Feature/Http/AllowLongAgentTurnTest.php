<?php

use App\Http\Middleware\AllowLongAgentTurn;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

it('raises the execution time limit for the rest of the request', function () {
    $original = ini_get('max_execution_time');
    set_time_limit(30);

    try {
        (new AllowLongAgentTurn)->handle(Request::create('/'), fn () => new Response);

        expect((int) ini_get('max_execution_time'))->toBe(AllowLongAgentTurn::SECONDS);
    } finally {
        set_time_limit((int) $original);
    }
});

it('covers every route that runs an agent turn inside the request', function (string $method, string $uri) {
    $route = Route::getRoutes()->match(Request::create($uri, $method));

    expect(Route::gatherRouteMiddleware($route))->toContain(AllowLongAgentTurn::class);
})->with([
    'send' => ['POST', '/api/v1/workspaces/w/agents/a/sessions/s/messages'],
    'public send' => ['POST', '/api/public/v1/agents/a/sessions/s/messages'],
]);
