<?php

use App\Exceptions\Billing\InsufficientCreditsException;
use App\Http\Middleware\ClampPagination;
use App\Http\Middleware\ResolveWorkspaceContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'workspace.role' => ResolveWorkspaceContext::class,
        ]);

        // Clamp pagination and apply a per-user rate limit to all API traffic.
        $middleware->api(append: [ClampPagination::class]);
        $middleware->throttleApi('api');

        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 401,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        // Consistent envelope for validation errors across the API.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 422,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Out-of-credits → 402 Payment Required with the standard envelope.
        $exceptions->render(function (InsufficientCreditsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'statusCode' => 402,
                    'message' => $e->getMessage(),
                ], 402);
            }
        });
    })->create();
