<?php

namespace App\Enums\SuperAdmin;

/**
 * Every action a Super Admin route can require. Which role holds which
 * ability is decided in `config/super_admin_roles.php`.
 */
enum SuperAdminAbility: string
{
    case StatsView = 'stats.view';
    case AuditView = 'audit.view';
    case UsersView = 'users.view';
    case UsersSuspend = 'users.suspend';
    case UsersForceLogout = 'users.force-logout';
    case CustomersAnnotate = 'customers.annotate';
    case WorkspacesView = 'workspaces.view';
    case WorkspacesSuspend = 'workspaces.suspend';
    case RunsView = 'runs.view';
    case RunsRetry = 'runs.retry';
    case RunsCancel = 'runs.cancel';
    case BillingView = 'billing.view';
    case BillingGrantCredits = 'billing.grant-credits';
    case BillingExtendPlan = 'billing.extend-plan';
    case BillingRefund = 'billing.refund';
    case BillingReplayWebhooks = 'billing.replay-webhooks';
    case CatalogManage = 'catalog.manage';
    case ReferralsManage = 'referrals.manage';
    case AccountsManage = 'accounts.manage';
    case SystemView = 'system.view';
    case SystemManage = 'system.manage';
    case AlertsView = 'alerts.view';
    case AlertsManage = 'alerts.manage';
    case RevenueView = 'revenue.view';
    case ModelsView = 'models.view';
    case ModelsManage = 'models.manage';
    case ModelsPrices = 'models.prices';
    case PricingView = 'pricing.view';
    case PricingManage = 'pricing.manage';
    case PricingPrices = 'pricing.prices';
    case BroadcastsView = 'broadcasts.view';
    case BroadcastsManage = 'broadcasts.manage';
}
