<?php

namespace App\Enums\Billing;

enum OverageInvoiceAttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
