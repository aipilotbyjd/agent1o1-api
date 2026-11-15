<?php

namespace App\Enums\Operations;

/**
 * `Manual` tasks are ticked off by a person. `Auto` tasks are checked by the
 * platform itself (see `OperationsCheck`) and cannot be ticked.
 */
enum TaskKind: string
{
    case Manual = 'manual';
    case Auto = 'auto';
}
