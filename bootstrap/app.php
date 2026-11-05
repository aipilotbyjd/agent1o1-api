<?php

use App\Exceptions\AiProviderConnectException;
use App\Exceptions\BillingAccountNotFoundException;
use App\Exceptions\ConnectorException;
use App\Exceptions\CostLimitReachedException;
use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\ModelBusyException;
use App\Exceptions\ModelNotOnPlanException;
use App\Exceptions\ModelSubmissionException;
use App\Exceptions\OwnAiKeyRequiredException;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\ProviderPausedException;
use App\Exceptions\PublishApprovalRequiredException;
use App\Exceptions\RunStateException;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Exceptions\WorkflowValidationException;
use App\Exceptions\WorkspaceSuspendedException;
use App\Http\Middleware\AllowLongAgentTurn;
use App\Http\Middleware\BlockWritesDuringMaintenance;
use App\Http\Middleware\EnsureApiKeyIsValid;
use App\Http\Middleware\EnsureWorkspaceScope;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuperAdmin\AuditSuperAdminRequest;
use App\Http\Middleware\SuperAdmin\EnsureSuperAdminAbility;
use App\Http\Middleware\SuperAdmin\EnsureSuperAdminIpAllowed;
use App\Http\Middleware\SuperAdmin\EnsureSuperAdminIsActive;
use App\Http\Middleware\SuperAdmin\EnsureSuperAdminTwoFactor;
use App\Http\Middleware\SuperAdmin\IdempotentSuperAdminRequest;
use App\Http\Middleware\TouchAccessTokenUsage;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api/internal/index.php',
            __DIR__.'/../routes/api/public/index.php',
            __DIR__.'/../routes/webhooks.php',
            __DIR__.'/../routes/api/pages.php',
            __DIR__.'/../routes/api/super_admin/index.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Registered explicitly rather than through `withRouting(channels: ...)`
    // so the authorization endpoint sits behind Passport on the API prefix
    // (POST /api/broadcasting/auth) — the default registration puts it behind
    // the session-based `web` guard, which this token-only API never uses.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        attributes: ['prefix' => 'api', 'middleware' => ['auth:api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'workspace.context' => EnsureWorkspaceScope::class,
            'api-key' => EnsureApiKeyIsValid::class,
            'long-agent-turn' => AllowLongAgentTurn::class,
            'super-admin.ip' => EnsureSuperAdminIpAllowed::class,
            'super-admin.active' => EnsureSuperAdminIsActive::class,
            'super-admin.two-factor' => EnsureSuperAdminTwoFactor::class,
            'super-admin.can' => EnsureSuperAdminAbility::class,
            'super-admin.audit' => AuditSuperAdminRequest::class,
            'super-admin.idempotent' => IdempotentSuperAdminRequest::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        $middleware->api(append: [AddQueuedCookiesToResponse::class, TouchAccessTokenUsage::class, BlockWritesDuringMaintenance::class]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::validationError($e->errors(), $e->getMessage());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::unauthorized($e->getMessage());
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::forbidden($e->getMessage());
            }
        });

        $exceptions->render(function (WorkflowValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $response = ApiResponse::validationError($e->errors(), $e->getMessage());

                return $response->setData([...$response->getData(true), 'issues' => $e->issues()]);
            }
        });

        $exceptions->render(function (PublishApprovalRequiredException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 409);
            }
        });

        $exceptions->render(function (RunStateException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 409);
            }
        });

        $exceptions->render(function (WorkflowBuilderConflictException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 409);
            }
        });

        $exceptions->render(function (ModelSubmissionException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error("The model didn't return a usable answer. Try again, or pick a different model.", 502);
            }
        });

        $exceptions->render(function (InsufficientCreditsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 402);
            }
        });

        $exceptions->render(function (CostLimitReachedException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 429);
            }
        });

        $exceptions->render(function (WorkspaceSuspendedException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::forbidden($e->getMessage());
            }
        });

        $exceptions->render(function (BillingAccountNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 409);
            }
        });

        $exceptions->render(function (PlanLimitExceededException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 402);
            }
        });

        $exceptions->render(function (ModelNotOnPlanException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 402, [
                    'reason' => 'plan_required',
                    'model' => $e->model->slug,
                    'required_plan' => ['slug' => $e->requiredPlan->slug, 'name' => $e->requiredPlan->name],
                ]);
            }
        });

        $exceptions->render(function (ModelBusyException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::tooManyRequests($e->getMessage())->header('Retry-After', (string) $e->retryAfter);
            }
        });

        $exceptions->render(function (ProviderPausedException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 503);
            }
        });

        $exceptions->render(function (OwnAiKeyRequiredException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        });

        $exceptions->render(function (AiProviderConnectException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        });

        $exceptions->render(function (ConnectorException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 422);
            }
        });

        $exceptions->render(function (FeatureNotAvailableException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::notFound('Resource not found');
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::notFound('Endpoint not found');
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::tooManyRequests($e->getMessage() ?: 'Too many requests');
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'Something went wrong', $e->getStatusCode());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::serverError(
                    config('app.debug') ? $e->getMessage() : 'Server error',
                );
            }
        });
    })->create();
