<?php

namespace App\Enums\Ai;

/**
 * What the provider last said about a stored key. Only a `Valid` key is
 * ever put in front of a call: see `ByokProviderRegistrar`.
 */
enum AiProviderCredentialStatus: string
{
    case Unvalidated = 'unvalidated';
    case Valid = 'valid';
    case Invalid = 'invalid';
}
