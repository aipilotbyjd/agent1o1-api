<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/auth.php';
    require __DIR__.'/notifications.php';
    require __DIR__.'/dashboard.php';
    require __DIR__.'/workspaces.php';
    require __DIR__.'/billing.php';
    require __DIR__.'/onboarding.php';
    require __DIR__.'/triggers.php';
    require __DIR__.'/workflows.php';
    require __DIR__.'/workflow_pages.php';
    require __DIR__.'/workflow_builder.php';
    require __DIR__.'/nodes.php';
    require __DIR__.'/model_catalog.php';
    require __DIR__.'/timezones.php';
    require __DIR__.'/ai_provider_credentials.php';
    require __DIR__.'/connectors.php';
    require __DIR__.'/secrets.php';
    require __DIR__.'/runs.php';
    require __DIR__.'/agents.php';
    require __DIR__.'/agent_actions.php';
    require __DIR__.'/assistant.php';
    require __DIR__.'/skills.php';
    require __DIR__.'/knowledge_base.php';
    require __DIR__.'/templates.php';
    require __DIR__.'/artifacts.php';
    require __DIR__.'/library.php';
    require __DIR__.'/referrals.php';
    require __DIR__.'/announcements.php';
});
