<?php

use App\Enums\SuperAdmin\SuperAdminAbility as Ability;
use App\Enums\SuperAdmin\SuperAdminRole as Role;

/*
|--------------------------------------------------------------------------
| Role → ability matrix
|--------------------------------------------------------------------------
|
| Roles and abilities are fixed in code on purpose: changing who can do what
| takes a reviewed pull request and a deploy, never an API call. "*" grants
| every ability, including ones added later.
|
*/

$view = [
    Ability::StatsView->value,
    Ability::AuditView->value,
    Ability::UsersView->value,
    Ability::WorkspacesView->value,
    Ability::RunsView->value,
    Ability::BillingView->value,
    Ability::ModelsView->value,
    Ability::PricingView->value,
    Ability::BroadcastsView->value,
    Ability::SystemView->value,
    Ability::AlertsView->value,
];

return [

    'roles' => [

        Role::Owner->value => ['*'],

        Role::Operator->value => [
            ...$view,
            Ability::UsersSuspend->value,
            Ability::UsersForceLogout->value,
            Ability::WorkspacesSuspend->value,
            Ability::CustomersAnnotate->value,
            Ability::RunsRetry->value,
            Ability::RunsCancel->value,
            Ability::CatalogManage->value,
            Ability::ReferralsManage->value,
            Ability::SystemManage->value,
            Ability::ModelsManage->value,
            Ability::BroadcastsManage->value,
            Ability::AlertsManage->value,
        ],

        Role::Finance->value => [
            ...$view,
            Ability::ReferralsManage->value,
            Ability::CustomersAnnotate->value,
            Ability::BillingGrantCredits->value,
            Ability::BillingExtendPlan->value,
            Ability::BillingRefund->value,
            Ability::BillingReplayWebhooks->value,
            Ability::ModelsPrices->value,
            Ability::RevenueView->value,
            Ability::PricingManage->value,
            Ability::PricingPrices->value,
        ],

        Role::Support->value => [
            ...$view,
            Ability::UsersSuspend->value,
            Ability::UsersForceLogout->value,
            Ability::WorkspacesSuspend->value,
            Ability::CustomersAnnotate->value,
            Ability::RunsRetry->value,
            Ability::RunsCancel->value,
        ],

        Role::Viewer->value => $view,

    ],

];
