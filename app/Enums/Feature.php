<?php

namespace App\Enums;

enum Feature: string
{
    case CreditPacks = 'credit_packs';
    case AnnualRollover = 'annual_rollover';
    case AuditLogs = 'audit_logs';
    case ApiAccess = 'api_access';
    case SsoSaml = 'sso_saml';
    case PrioritySupport = 'priority_support';
    case CustomDomain = 'custom_domain';
    case Webhooks = 'webhooks';
}
