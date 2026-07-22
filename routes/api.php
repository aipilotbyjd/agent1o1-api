<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AdminSettingsController;
use App\Http\Controllers\Api\V1\AgentAnalyticsController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AgentConversationController;
use App\Http\Controllers\Api\V1\AgentEvalController;
use App\Http\Controllers\Api\V1\AgentKnowledgeController;
use App\Http\Controllers\Api\V1\AgentMemoryController;
use App\Http\Controllers\Api\V1\AgentMetadataController;
use App\Http\Controllers\Api\V1\AgentRunController;
use App\Http\Controllers\Api\V1\AgentSkillController;
use App\Http\Controllers\Api\V1\AgentTemplateController;
use App\Http\Controllers\Api\V1\AgentTriggerController;
use App\Http\Controllers\Api\V1\AgentVersionController;
use App\Http\Controllers\Api\V1\AiAutofixController;
use App\Http\Controllers\Api\V1\ArchivedExecutionController;
use App\Http\Controllers\Api\V1\ArtifactController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ConnectorMetricController;
use App\Http\Controllers\Api\V1\CredentialController;
use App\Http\Controllers\Api\V1\CredentialTypeController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExecutionController;
use App\Http\Controllers\Api\V1\ExecutionLogController;
use App\Http\Controllers\Api\V1\ExecutionReplayController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\GitSyncController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\LogStreamingConfigController;
use App\Http\Controllers\Api\V1\NodeCategoryController;
use App\Http\Controllers\Api\V1\NodeController;
use App\Http\Controllers\Api\V1\NodeLibraryController;
use App\Http\Controllers\Api\V1\NodeOutputSchemaController;
use App\Http\Controllers\Api\V1\NodeSandboxController;
use App\Http\Controllers\Api\V1\NodeTestController;
use App\Http\Controllers\Api\V1\NotificationChannelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OAuthCredentialController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PinnedNodeDataController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\RunController;
use App\Http\Controllers\Api\V1\SharedWorkflowController;
use App\Http\Controllers\Api\V1\StickyNoteController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TemplateCollectionController;
use App\Http\Controllers\Api\V1\TriggerCatalogController;
use App\Http\Controllers\Api\V1\TriggerController;
use App\Http\Controllers\Api\V1\TriggerEventController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VariableController;
use App\Http\Controllers\Api\V1\VectorStoreController;
use App\Http\Controllers\Api\V1\WorkflowApprovalController;
use App\Http\Controllers\Api\V1\WorkflowBuilder\DraftVersionController;
use App\Http\Controllers\Api\V1\WorkflowBuilder\GenerationController;
use App\Http\Controllers\Api\V1\WorkflowBuilder\MessageController as BuilderMessageController;
use App\Http\Controllers\Api\V1\WorkflowBuilder\SessionController as BuilderSessionController;
use App\Http\Controllers\Api\V1\WorkflowContractController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\WorkflowEnvironmentReleaseController;
use App\Http\Controllers\Api\V1\WorkflowImportExportController;
use App\Http\Controllers\Api\V1\WorkflowShareController;
use App\Http\Controllers\Api\V1\WorkflowTemplateController;
use App\Http\Controllers\Api\V1\WorkflowVersionController;
use App\Http\Controllers\Api\V1\WorkspaceAccessController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceEnvironmentController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use App\Http\Controllers\Webhooks\GitSyncWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\TriggerWebhookController;
use App\Http\Controllers\Webhooks\WaitWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('v1.')->group(function () {
    /*
    |----------------------------------------------------------------------
    | Public — no authentication required
    |----------------------------------------------------------------------
    */

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:auth'])
        ->name('verification.verify');

    Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->name('webhooks.stripe');

    Route::match(['GET', 'POST'], 'webhooks/{webhookUuid}', [TriggerWebhookController::class, 'receive'])
        ->where('webhookUuid', '[0-9a-f\-]{36}')
        ->middleware('throttle:60,1')
        ->name('webhooks.trigger');

    // Agent webhooks are unified into the trigger webhook endpoint above; an
    // agent-targeted Trigger resolves by its webhook_uuid like any other.

    Route::match(['GET', 'POST'], 'webhook-wait/{token}', [WaitWebhookController::class, 'resume'])
        ->where('token', '[0-9a-f\-]{36}')
        ->middleware('throttle:60,1')
        ->name('webhook-wait.resume');

    Route::match(['GET', 'POST'], 'oauth-credentials/callback', [OAuthCredentialController::class, 'callback'])
        ->name('oauth-credentials.callback');

    Route::post('git-sync/webhook/{config}', [GitSyncWebhookController::class, 'receive'])
        ->where('config', '[0-9a-f\-]{36}')
        ->middleware('throttle:60,1')
        ->name('git-sync.webhook');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

    Route::prefix('agent-templates')->as('agent-templates.')->group(function () {
        Route::get('/', [AgentTemplateController::class, 'index'])->name('index');
        Route::get('{agentTemplate}', [AgentTemplateController::class, 'show'])->name('show');
    });

    Route::prefix('workflow-templates')->as('workflow-templates.')->group(function () {
        Route::get('/', [WorkflowTemplateController::class, 'index'])->name('index');
        Route::get('{workflowTemplate}', [WorkflowTemplateController::class, 'show'])->name('show');
    });

    Route::prefix('template-collections')->as('template-collections.')->group(function () {
        Route::get('/', [TemplateCollectionController::class, 'index'])->name('index');
        Route::get('{templateCollection}', [TemplateCollectionController::class, 'show'])->name('show');
    });

    Route::get('shared/{token}', [SharedWorkflowController::class, 'show'])->name('shared.show');

    Route::get('artifacts/{artifact}/preview', [ArtifactController::class, 'preview'])
        ->middleware(['signed', 'throttle:60,1'])
        ->name('artifacts.preview');

    /*
    |----------------------------------------------------------------------
    | Guest — authentication routes for unauthenticated users
    |----------------------------------------------------------------------
    */

    Route::prefix('auth')->as('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->name('login');
        Route::post('refresh', RefreshTokenController::class)->name('refresh');
        Route::post('forgot-password', ForgotPasswordController::class)->name('forgot-password');
        Route::post('reset-password', ResetPasswordController::class)->name('reset-password');
    });

    /*
    |----------------------------------------------------------------------
    | Authenticated — requires a valid access token
    |----------------------------------------------------------------------
    */

    Route::middleware('auth:api')->group(function () {
        Route::prefix('auth')->as('auth.')->group(function () {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('resend-verification-email', ResendVerificationController::class)->name('resend-verification');
        });

        Route::prefix('user')->as('user.')->group(function () {
            Route::get('/', [UserController::class, 'me'])->name('me');
            Route::patch('/', [UserController::class, 'update'])->name('update');
            Route::delete('/', [UserController::class, 'destroy'])->name('destroy');
            Route::post('change-password', [UserController::class, 'changePassword'])->name('change-password');
            Route::post('avatar', [UserController::class, 'uploadAvatar'])->name('upload-avatar');
            Route::delete('avatar', [UserController::class, 'deleteAvatar'])->name('delete-avatar');
            Route::post('switch-workspace', [UserController::class, 'switchWorkspace'])->name('switch-workspace');
            Route::post('dismiss-onboarding', [UserController::class, 'dismissOnboarding'])->name('dismiss-onboarding');
            Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        });

        /*
        |------------------------------------------------------------------
        | Onboarding wizard steps
        |------------------------------------------------------------------
        */
        Route::prefix('onboarding')->as('onboarding.')->group(function () {
            Route::post('role', [OnboardingController::class, 'saveRole'])->name('role');
            Route::post('discovery', [OnboardingController::class, 'saveDiscovery'])->name('discovery');
            Route::post('invite-team', [OnboardingController::class, 'inviteTeam'])->name('invite-team');
            Route::post('plan', [OnboardingController::class, 'selectPlan'])->name('plan');
            Route::post('complete', [OnboardingController::class, 'complete'])->name('complete');
        });

        Route::post('shared/{token}/clone', [SharedWorkflowController::class, 'clone'])->name('shared.clone');

        /*
        |------------------------------------------------------------------
        | Node Library — global catalog (read-only, no workspace required)
        |------------------------------------------------------------------
        */

        Route::prefix('nodes')->as('nodes.')->group(function () {
            Route::get('/', [NodeController::class, 'index'])->name('index');
            Route::get('{node}', [NodeController::class, 'show'])->name('show');
        });

        Route::prefix('node-categories')->as('node-categories.')->group(function () {
            Route::get('/', [NodeCategoryController::class, 'index'])->name('index');
            Route::get('{nodeCategory}', [NodeCategoryController::class, 'show'])->name('show');
        });

        Route::prefix('credential-types')->as('credential-types.')->group(function () {
            Route::get('/', [CredentialTypeController::class, 'index'])->name('index');
            Route::get('{credentialType}', [CredentialTypeController::class, 'show'])->name('show');
        });

        /*
        |------------------------------------------------------------------
        | Agent template catalog — platform-admin management
        |------------------------------------------------------------------
        */

        Route::prefix('agent-templates')->as('agent-templates.')->group(function () {
            Route::post('/', [AgentTemplateController::class, 'store'])->name('store');
            Route::put('{agentTemplate}', [AgentTemplateController::class, 'update'])->name('update');
            Route::delete('{agentTemplate}', [AgentTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('workflow-templates')->as('workflow-templates.')->group(function () {
            Route::post('/', [WorkflowTemplateController::class, 'store'])->name('store');
            Route::delete('{workflowTemplate}', [WorkflowTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('admin')->as('admin.')->group(function () {
            Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        });

        /*
        |------------------------------------------------------------------
        | Invitations — by token, membership not required
        |------------------------------------------------------------------
        */

        Route::prefix('invitations/{token}')->as('invitations.')->group(function () {
            Route::post('accept', [InvitationController::class, 'accept'])->name('accept');
            Route::post('decline', [InvitationController::class, 'decline'])->name('decline');
        });

        /*
        |------------------------------------------------------------------
        | Workspaces — list + create (no membership required)
        |------------------------------------------------------------------
        */

        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

        /*
        |------------------------------------------------------------------
        | Workspace — membership required, role resolved once
        |------------------------------------------------------------------
        */

        Route::prefix('workspaces/{workspace}')->as('workspaces.')
            ->middleware('workspace.role')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', [WorkspaceController::class, 'show'])->name('show');
                Route::put('/', [WorkspaceController::class, 'update'])->name('update');
                Route::delete('/', [WorkspaceController::class, 'destroy'])->name('destroy');

                Route::get('me/access', WorkspaceAccessController::class)->name('me.access');

                Route::prefix('members')->as('members.')->group(function () {
                    Route::get('/', [WorkspaceMemberController::class, 'index'])->name('index');
                    Route::put('{user}', [WorkspaceMemberController::class, 'update'])->name('update');
                    Route::delete('{user}', [WorkspaceMemberController::class, 'destroy'])->name('destroy');
                });

                Route::post('transfer-ownership', [WorkspaceMemberController::class, 'transferOwnership'])
                    ->name('transfer-ownership');
                Route::post('leave', [WorkspaceMemberController::class, 'leave'])->name('leave');

                Route::prefix('invitations')->as('invitations.')->group(function () {
                    Route::get('/', [InvitationController::class, 'index'])->name('index');
                    Route::post('/', [InvitationController::class, 'store'])->name('store');
                    Route::delete('{invitation}', [InvitationController::class, 'destroy'])->name('destroy');
                });

                /*
                |--------------------------------------------------------------
                | Billing & Credits (Phase 4)
                |--------------------------------------------------------------
                */

                Route::prefix('subscription')->as('subscription.')->group(function () {
                    Route::get('/', [SubscriptionController::class, 'show'])->name('show');
                    Route::post('checkout', [SubscriptionController::class, 'checkout'])->name('checkout');
                    Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
                    Route::post('resume', [SubscriptionController::class, 'resume'])->name('resume');
                    Route::get('portal', [SubscriptionController::class, 'portal'])->name('portal');
                });

                Route::prefix('billing')->as('billing.')->group(function () {
                    Route::get('packs', [BillingController::class, 'packCatalog'])->name('packs.index');
                    Route::post('packs', [BillingController::class, 'packCheckout'])->name('packs.checkout');
                });

                Route::prefix('credits')->as('credits.')->group(function () {
                    Route::get('balance', [CreditController::class, 'balance'])->name('balance');
                    Route::get('transactions', [CreditController::class, 'transactions'])->name('transactions');
                    Route::get('packs', [CreditController::class, 'packs'])->name('packs');
                });

                Route::get('usage-snapshots', [CreditController::class, 'usageSnapshots'])->name('usage-snapshots');

                /*
                |--------------------------------------------------------------
                | Workflow Engine
                |--------------------------------------------------------------
                */

                Route::prefix('workflows')->as('workflows.')->group(function () {
                    Route::get('/', [WorkflowController::class, 'index'])->name('index');
                    Route::post('/', [WorkflowController::class, 'store'])->name('store');
                    // Literal route registered before the {workflow} params so
                    // "test-node" is never captured as a workflow id.
                    Route::post('test-node', [NodeTestController::class, 'test'])->name('test-node');
                    Route::get('{workflow}', [WorkflowController::class, 'show'])->name('show');
                    Route::put('{workflow}', [WorkflowController::class, 'update'])->name('update');
                    Route::delete('{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
                    Route::post('{workflow}/activate', [WorkflowController::class, 'activate'])->name('activate');
                    Route::post('{workflow}/deactivate', [WorkflowController::class, 'deactivate'])->name('deactivate');
                    Route::post('{workflow}/duplicate', [WorkflowController::class, 'duplicate'])->name('duplicate');
                    Route::post('{workflow}/execute', [ExecutionController::class, 'store'])->name('execute');

                    Route::prefix('{workflow}/triggers')->as('triggers.')->group(function () {
                        Route::get('/', [TriggerController::class, 'index'])->name('index');
                        Route::post('/', [TriggerController::class, 'store'])->name('store');
                        Route::get('{trigger}', [TriggerController::class, 'show'])->name('show');
                        Route::delete('{trigger}', [TriggerController::class, 'destroy'])->name('destroy');
                        Route::post('{trigger}/pause', [TriggerController::class, 'pause'])->name('pause');
                        Route::post('{trigger}/resume', [TriggerController::class, 'resume'])->name('resume');
                        Route::put('{trigger}/polling-interval', [TriggerController::class, 'setPollingInterval'])->name('polling-interval');
                        Route::put('{trigger}/schedule', [TriggerController::class, 'setSchedule'])->name('schedule');
                    });

                    Route::prefix('{workflow}/sticky-notes')->as('sticky-notes.')->group(function () {
                        Route::get('/', [StickyNoteController::class, 'index'])->name('index');
                        Route::post('/', [StickyNoteController::class, 'store'])->name('store');
                        Route::put('{stickyNote}', [StickyNoteController::class, 'update'])->name('update');
                        Route::delete('{stickyNote}', [StickyNoteController::class, 'destroy'])->name('destroy');
                    });

                    Route::prefix('{workflow}/versions')->as('versions.')->group(function () {
                        Route::get('/', [WorkflowVersionController::class, 'index'])->name('index');
                        Route::get('diff/{from}/{to}', [WorkflowVersionController::class, 'diff'])->name('diff');
                        Route::get('{version}', [WorkflowVersionController::class, 'show'])->name('show');
                        Route::post('{version}/publish', [WorkflowVersionController::class, 'publish'])->name('publish');
                        Route::post('{version}/rollback', [WorkflowVersionController::class, 'rollback'])->name('rollback');
                    });

                    Route::prefix('{workflow}/pinned-data')->as('pinned-data.')->group(function () {
                        Route::get('/', [PinnedNodeDataController::class, 'index'])->name('index');
                        Route::post('/', [PinnedNodeDataController::class, 'store'])->name('store');
                        Route::delete('{nodeId}', [PinnedNodeDataController::class, 'destroy'])->name('destroy');
                    });

                    Route::prefix('{workflow}/shares')->as('shares.')->group(function () {
                        Route::get('/', [WorkflowShareController::class, 'index'])->name('index');
                        Route::post('/', [WorkflowShareController::class, 'store'])->name('store');
                        Route::delete('{share}', [WorkflowShareController::class, 'destroy'])->name('destroy');
                    });

                    Route::get('{workflow}/nodes/{nodeId}/output-schema', [NodeOutputSchemaController::class, 'show'])->name('nodes.output-schema');

                    Route::get('{workflow}/export', [WorkflowImportExportController::class, 'export'])->name('export');

                    Route::post('import', [WorkflowImportExportController::class, 'import'])
                        ->withoutScopedBindings()
                        ->name('import');

                    Route::prefix('{workflow}/releases')->as('releases.')->group(function () {
                        Route::get('/', [WorkflowEnvironmentReleaseController::class, 'index'])->name('index');
                        Route::post('/', [WorkflowEnvironmentReleaseController::class, 'release'])->name('store');
                    });

                    Route::prefix('{workflow}/approvals')->as('approvals.')->group(function () {
                        Route::get('/', [WorkflowApprovalController::class, 'index'])->name('index');
                        Route::post('/', [WorkflowApprovalController::class, 'request'])->name('request');
                        Route::post('{approval}/approve', [WorkflowApprovalController::class, 'approve'])->name('approve');
                        Route::post('{approval}/reject', [WorkflowApprovalController::class, 'reject'])->name('reject');
                    });

                    Route::prefix('{workflow}/contracts')->as('contracts.')->group(function () {
                        Route::get('/', [WorkflowContractController::class, 'index'])->name('index');
                        Route::post('/', [WorkflowContractController::class, 'generate'])->name('generate');
                        Route::post('{contract}/run', [WorkflowContractController::class, 'run'])->name('run');
                    });
                });

                /*
                |--------------------------------------------------------------
                | Node Library — workspace helpers
                |--------------------------------------------------------------
                */

                Route::prefix('nodes')->as('nodes.')->group(function () {
                    Route::post('sandbox', [NodeSandboxController::class, 'execute'])->name('sandbox');
                    Route::get('recently-used', [NodeLibraryController::class, 'recentlyUsed'])->name('recently-used');
                    Route::get('custom', [NodeLibraryController::class, 'customNodes'])->name('custom');
                });

                /*
                |--------------------------------------------------------------
                | AI Workflow Builder
                |--------------------------------------------------------------
                */

                Route::prefix('workflow-builder')->as('workflow-builder.')->group(function () {
                    // One-shot generation (no session required)
                    Route::post('/', [GenerationController::class, 'build'])->name('build')->middleware('throttle:5,1');
                    Route::post('explain', [GenerationController::class, 'explain'])->name('explain')->middleware('throttle:20,1');
                    Route::post('suggest-nodes', [GenerationController::class, 'suggestNodes'])->name('suggest-nodes')->middleware('throttle:20,1');
                    Route::post('configure-node', [GenerationController::class, 'configureNode'])->name('configure-node')->middleware('throttle:20,1');
                    Route::post('suggest-enhancements', [GenerationController::class, 'suggestEnhancements'])->name('suggest-enhancements')->middleware('throttle:20,1');

                    // Sessions
                    Route::prefix('sessions')->as('sessions.')->group(function () {
                        Route::get('/', [BuilderSessionController::class, 'index'])->name('index');
                        Route::post('/', [BuilderSessionController::class, 'store'])->name('store');
                        Route::get('{builderSession}', [BuilderSessionController::class, 'show'])->name('show');
                        Route::patch('{builderSession}', [BuilderSessionController::class, 'update'])->name('update');
                        Route::delete('{builderSession}', [BuilderSessionController::class, 'destroy'])->name('destroy');
                        Route::post('{builderSession}/validate', [BuilderSessionController::class, 'validate'])->name('validate');
                        Route::post('{builderSession}/save', [BuilderSessionController::class, 'save'])->name('save');
                        Route::patch('{builderSession}/draft', [BuilderSessionController::class, 'syncDraft'])->name('draft')->middleware('throttle:30,1');

                        // Conversational messages
                        Route::prefix('{builderSession}/messages')->as('messages.')->group(function () {
                            Route::get('/', [BuilderMessageController::class, 'index'])->name('index');
                            Route::post('/', [BuilderMessageController::class, 'store'])->name('store')->middleware('throttle:10,1');
                        });

                        // Draft versions (undo / redo)
                        Route::prefix('{builderSession}/versions')->as('versions.')->group(function () {
                            Route::get('/', [DraftVersionController::class, 'index'])->name('index');
                            Route::post('{version}/restore', [DraftVersionController::class, 'restore'])->name('restore');
                        });
                    });
                });

                // Unified cross-type run view (workflow executions + agent runs).
                Route::prefix('runs')->as('runs.')->group(function () {
                    Route::get('/', [RunController::class, 'index'])->name('index');
                    Route::get('{run}', [RunController::class, 'show'])->name('show');
                });

                Route::prefix('executions')->as('executions.')->group(function () {
                    Route::get('/', [ExecutionController::class, 'index'])->name('index');
                    Route::get('{execution}', [ExecutionController::class, 'show'])->name('show');
                    Route::delete('{execution}', [ExecutionController::class, 'destroy'])->name('destroy');
                    Route::get('{execution}/nodes', [ExecutionController::class, 'nodes'])->name('nodes');
                    Route::post('{execution}/retry', [ExecutionController::class, 'retry'])->name('retry');
                    Route::post('{execution}/cancel', [ExecutionController::class, 'cancel'])->name('cancel');
                    Route::get('{execution}/logs', [ExecutionLogController::class, 'index'])->name('logs');
                    Route::post('{execution}/replay-pack', [ExecutionReplayController::class, 'store'])->name('replay-pack.store');

                    Route::get('{execution}/autofix', [AiAutofixController::class, 'index'])->name('autofix.index');
                    Route::post('{execution}/autofix', [AiAutofixController::class, 'diagnose'])->name('autofix.diagnose');
                    Route::post('{execution}/autofix/{fixSuggestion}/apply', [AiAutofixController::class, 'apply'])->name('autofix.apply');
                    Route::post('{execution}/autofix/{fixSuggestion}/dismiss', [AiAutofixController::class, 'dismiss'])->name('autofix.dismiss');
                });

                Route::prefix('replay-packs')->as('replay-packs.')->group(function () {
                    Route::get('/', [ExecutionReplayController::class, 'index'])->name('index');
                    Route::post('{replayPack}/replay', [ExecutionReplayController::class, 'replay'])->name('replay');
                });

                Route::get('archived-logs', [ArchivedExecutionController::class, 'index'])->name('archived-logs.index');

                Route::get('trigger-catalog', [TriggerCatalogController::class, 'index'])->name('trigger-catalog.index');

                Route::prefix('triggers/{trigger}/events')->as('trigger-events.')->group(function () {
                    Route::get('/', [TriggerEventController::class, 'index'])->name('index');
                    Route::post('{event}/replay', [TriggerEventController::class, 'replay'])->name('replay');
                });

                Route::prefix('credentials')->as('credentials.')->group(function () {
                    Route::post('oauth/initiate', [OAuthCredentialController::class, 'initiate'])->name('oauth.initiate');
                    Route::get('/', [CredentialController::class, 'index'])->name('index');
                    Route::post('/', [CredentialController::class, 'store'])->name('store');
                    Route::get('{credential}', [CredentialController::class, 'show'])->name('show');
                    Route::put('{credential}', [CredentialController::class, 'update'])->name('update');
                    Route::delete('{credential}', [CredentialController::class, 'destroy'])->name('destroy');
                    Route::post('{credential}/test', [CredentialController::class, 'test'])->name('test');
                });

                Route::prefix('variables')->as('variables.')->group(function () {
                    Route::get('/', [VariableController::class, 'index'])->name('index');
                    Route::post('/', [VariableController::class, 'store'])->name('store');
                    Route::get('{variable}', [VariableController::class, 'show'])->name('show');
                    Route::put('{variable}', [VariableController::class, 'update'])->name('update');
                    Route::delete('{variable}', [VariableController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('folders')->as('folders.')->group(function () {
                    Route::get('/', [FolderController::class, 'index'])->name('index');
                    Route::post('/', [FolderController::class, 'store'])->name('store');
                    Route::post('move-workflows', [FolderController::class, 'moveWorkflows'])->name('move-workflows');
                    Route::put('{folder}', [FolderController::class, 'update'])->name('update');
                    Route::delete('{folder}', [FolderController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('tags')->as('tags.')->group(function () {
                    Route::get('/', [TagController::class, 'index'])->name('index');
                    Route::post('/', [TagController::class, 'store'])->name('store');
                    Route::put('{tag}', [TagController::class, 'update'])->name('update');
                    Route::delete('{tag}', [TagController::class, 'destroy'])->name('destroy');
                });

                /*
                |--------------------------------------------------------------
                | AI Agents (Phase 6)
                |--------------------------------------------------------------
                */

                Route::prefix('agents')->as('agents.')->group(function () {
                    // Builder metadata — providers, models, tool catalog, categories, trigger types.
                    Route::prefix('meta')->as('meta.')->group(function () {
                        Route::get('providers', [AgentMetadataController::class, 'providers'])->name('providers');
                        Route::get('models', [AgentMetadataController::class, 'models'])->name('models');
                        Route::get('tools', [AgentMetadataController::class, 'tools'])->name('tools');
                        Route::get('categories', [AgentMetadataController::class, 'categories'])->name('categories');
                        Route::get('trigger-types', [AgentMetadataController::class, 'triggerTypes'])->name('trigger-types');
                        Route::get('connectors', [AgentMetadataController::class, 'connectors'])->name('connectors');
                    });

                    Route::get('/', [AgentController::class, 'index'])->name('index');
                    Route::post('/', [AgentController::class, 'store'])->name('store');
                    Route::get('{agent}', [AgentController::class, 'show'])->name('show');
                    Route::put('{agent}', [AgentController::class, 'update'])->name('update');
                    Route::delete('{agent}', [AgentController::class, 'destroy'])->name('destroy');
                    Route::post('{agent}/duplicate', [AgentController::class, 'duplicate'])->name('duplicate');
                    Route::post('{agent}/pause', [AgentController::class, 'pause'])->name('pause');
                    Route::post('{agent}/resume', [AgentController::class, 'resume'])->name('resume');
                    Route::post('{agent}/skills/attach', [AgentController::class, 'attachSkill'])->name('skills.attach');
                    Route::delete('{agent}/skills/{skillId}', [AgentController::class, 'detachSkill'])->name('skills.detach');

                    Route::prefix('{agent}/conversations')->as('conversations.')->group(function () {
                        Route::get('/', [AgentConversationController::class, 'index'])->name('index');
                        Route::post('/', [AgentConversationController::class, 'store'])->name('store');
                        Route::get('{conversation}', [AgentConversationController::class, 'show'])->name('show');
                        Route::delete('{conversation}', [AgentConversationController::class, 'destroy'])->name('destroy');
                        Route::post('{conversation}/messages', [AgentConversationController::class, 'sendMessage'])->name('messages.store');
                    });

                    Route::prefix('{agent}/triggers')->as('triggers.')->group(function () {
                        Route::get('/', [AgentTriggerController::class, 'index'])->name('index');
                        Route::post('/', [AgentTriggerController::class, 'store'])->name('store');
                        Route::put('{trigger}', [AgentTriggerController::class, 'update'])->name('update');
                        Route::delete('{trigger}', [AgentTriggerController::class, 'destroy'])->name('destroy');
                        Route::post('{trigger}/fire', [AgentTriggerController::class, 'fire'])->name('fire');
                    });

                    // Poll a queued message request's status (non-WebSocket clients).
                    Route::get('{agent}/requests/{requestId}', [AgentConversationController::class, 'requestStatus'])
                        ->name('requests.show');

                    // Run history & step traces.
                    Route::prefix('{agent}/runs')->as('runs.')->group(function () {
                        Route::get('/', [AgentRunController::class, 'index'])->name('index');
                        Route::get('{run}', [AgentRunController::class, 'show'])->name('show');
                    });

                    // Usage analytics.
                    Route::get('{agent}/analytics', [AgentAnalyticsController::class, 'show'])->name('analytics');

                    // Knowledge base (RAG grounding).
                    Route::prefix('{agent}/knowledge')->as('knowledge.')->group(function () {
                        Route::get('/', [AgentKnowledgeController::class, 'index'])->name('index');
                        Route::post('/', [AgentKnowledgeController::class, 'store'])->name('store');
                        Route::get('{knowledge}', [AgentKnowledgeController::class, 'show'])->name('show');
                        Route::put('{knowledge}', [AgentKnowledgeController::class, 'update'])->name('update');
                        Route::delete('{knowledge}', [AgentKnowledgeController::class, 'destroy'])->name('destroy');
                    });

                    // Persistent memory.
                    Route::prefix('{agent}/memories')->as('memories.')->group(function () {
                        Route::get('/', [AgentMemoryController::class, 'index'])->name('index');
                        Route::post('/', [AgentMemoryController::class, 'store'])->name('store');
                        Route::delete('/', [AgentMemoryController::class, 'clear'])->name('clear');
                        Route::delete('{memory}', [AgentMemoryController::class, 'destroy'])->name('destroy');
                    });

                    // Versioning & rollback (roadmap item 10).
                    Route::prefix('{agent}/versions')->as('versions.')->group(function () {
                        Route::get('/', [AgentVersionController::class, 'index'])->name('index');
                        Route::get('{version}', [AgentVersionController::class, 'show'])->name('show');
                        Route::get('{version}/diff', [AgentVersionController::class, 'diff'])->name('diff');
                        Route::post('{version}/rollback', [AgentVersionController::class, 'rollback'])->name('rollback');
                    });

                    // Eval/testing framework (roadmap item 9).
                    Route::prefix('{agent}/eval-suites')->as('eval-suites.')->group(function () {
                        Route::get('/', [AgentEvalController::class, 'index'])->name('index');
                        Route::post('/', [AgentEvalController::class, 'store'])->name('store');
                        Route::get('{evalSuite}', [AgentEvalController::class, 'show'])->name('show');
                        Route::delete('{evalSuite}', [AgentEvalController::class, 'destroy'])->name('destroy');
                        Route::post('{evalSuite}/cases', [AgentEvalController::class, 'addCase'])->name('cases.store');
                        Route::delete('{evalSuite}/cases/{caseId}', [AgentEvalController::class, 'destroyCase'])->name('cases.destroy');
                        Route::post('{evalSuite}/run', [AgentEvalController::class, 'run'])->name('run');
                        Route::get('{evalSuite}/runs', [AgentEvalController::class, 'runs'])->name('runs');
                    });
                });

                Route::prefix('agent-skills')->as('agent-skills.')->group(function () {
                    Route::get('/', [AgentSkillController::class, 'index'])->name('index');
                    Route::post('/', [AgentSkillController::class, 'store'])->name('store');
                    Route::post('generate', [AgentSkillController::class, 'generate'])->name('generate')->middleware('throttle:10,1');
                    Route::get('{agentSkill}', [AgentSkillController::class, 'show'])->name('show');
                    Route::put('{agentSkill}', [AgentSkillController::class, 'update'])->name('update');
                    Route::delete('{agentSkill}', [AgentSkillController::class, 'destroy'])->name('destroy');

                    Route::post('{agentSkill}/references', [AgentSkillController::class, 'addReference'])->name('references.store');
                    Route::put('{agentSkill}/references/{reference}', [AgentSkillController::class, 'updateReference'])->name('references.update');
                    Route::delete('{agentSkill}/references/{reference}', [AgentSkillController::class, 'removeReference'])->name('references.destroy');

                    Route::post('{agentSkill}/scripts', [AgentSkillController::class, 'addScript'])->name('scripts.store');
                    Route::put('{agentSkill}/scripts/{script}', [AgentSkillController::class, 'updateScript'])->name('scripts.update');
                    Route::delete('{agentSkill}/scripts/{script}', [AgentSkillController::class, 'removeScript'])->name('scripts.destroy');
                });

                Route::prefix('artifacts')->as('artifacts.')->group(function () {
                    Route::get('/', [ArtifactController::class, 'index'])->name('index');
                    Route::get('{artifact}', [ArtifactController::class, 'show'])->name('show');
                    Route::delete('{artifact}', [ArtifactController::class, 'destroy'])->name('destroy');
                    Route::get('{artifact}/download', [ArtifactController::class, 'download'])->name('download');
                });

                Route::post('agent-templates/{agentTemplate}/deploy', [AgentTemplateController::class, 'deploy'])
                    ->withoutScopedBindings()
                    ->name('agent-templates.deploy');

                Route::post('workflow-templates/{workflowTemplate}/deploy', [WorkflowTemplateController::class, 'deploy'])
                    ->withoutScopedBindings()
                    ->name('workflow-templates.deploy');

                /*
                |--------------------------------------------------------------
                | Notifications, audit & monitoring (Phase 10)
                |--------------------------------------------------------------
                */

                Route::prefix('notifications')->as('notifications.')->group(function () {
                    Route::get('/', [NotificationController::class, 'index'])->name('index');
                    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                    Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
                    Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
                    Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('notification-channels')->as('notification-channels.')->group(function () {
                    Route::get('/', [NotificationChannelController::class, 'index'])->name('index');
                    Route::post('/', [NotificationChannelController::class, 'store'])->name('store');
                    Route::put('{notificationChannel}', [NotificationChannelController::class, 'update'])->name('update');
                    Route::delete('{notificationChannel}', [NotificationChannelController::class, 'destroy'])->name('destroy');
                    Route::post('{notificationChannel}/test', [NotificationChannelController::class, 'test'])->name('test');
                });

                Route::prefix('notification-preferences')->as('notification-preferences.')->group(function () {
                    Route::get('/', [NotificationPreferenceController::class, 'index'])->name('index');
                    Route::put('/', [NotificationPreferenceController::class, 'upsert'])->name('upsert');
                });

                Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

                Route::prefix('connector-metrics')->as('connector-metrics.')->group(function () {
                    Route::get('/', [ConnectorMetricController::class, 'index'])->name('index');
                    Route::get('summary', [ConnectorMetricController::class, 'summary'])->name('summary');
                });

                Route::prefix('dashboard')->as('dashboard.')->group(function () {
                    Route::get('/', [DashboardController::class, 'index'])->name('index');
                    Route::get('overview', [DashboardController::class, 'overview'])->name('overview');
                    Route::get('trends', [DashboardController::class, 'trends'])->name('trends');
                    Route::get('top-workflows', [DashboardController::class, 'topWorkflows'])->name('top-workflows');
                    Route::get('recent-activity', [DashboardController::class, 'recentActivity'])->name('recent-activity');
                });

                Route::get('stats', [DashboardController::class, 'quickStats'])->name('stats');

                Route::prefix('log-streaming')->as('log-streaming.')->group(function () {
                    Route::get('/', [LogStreamingConfigController::class, 'index'])->name('index');
                    Route::post('/', [LogStreamingConfigController::class, 'store'])->name('store');
                    Route::put('{logStreamingConfig}', [LogStreamingConfigController::class, 'update'])->name('update');
                    Route::delete('{logStreamingConfig}', [LogStreamingConfigController::class, 'destroy'])->name('destroy');
                });

                /*
                |--------------------------------------------------------------
                | Enterprise — environments, git sync, RAG (Phase 12)
                |--------------------------------------------------------------
                */

                Route::prefix('environments')->as('environments.')->group(function () {
                    Route::get('/', [WorkspaceEnvironmentController::class, 'index'])->name('index');
                    Route::post('/', [WorkspaceEnvironmentController::class, 'store'])->name('store');
                    Route::put('{environment}', [WorkspaceEnvironmentController::class, 'update'])->name('update');
                    Route::delete('{environment}', [WorkspaceEnvironmentController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('git-sync')->as('git-sync.')->group(function () {
                    Route::get('/', [GitSyncController::class, 'show'])->name('show');
                    Route::put('/', [GitSyncController::class, 'configure'])->name('configure');
                    Route::post('export', [GitSyncController::class, 'export'])->name('export');
                    Route::post('import', [GitSyncController::class, 'import'])->name('import');
                });

                Route::prefix('vector-store')->as('vector-store.')->group(function () {
                    Route::post('ingest', [VectorStoreController::class, 'ingest'])->name('ingest');
                    Route::post('query', [VectorStoreController::class, 'query'])->name('query');
                });
            });
    });
});
