<?php

namespace App\Contracts;

use App\Enums\Agents\ActionEffect;

/**
 * A node that says what calling it does to the world, which is what an
 * agent's autonomy mode and approval rules are decided on (see
 * `Services\Agents\Approvals\ActionGate`). A node without it is treated as
 * a `Write`: unknown means "ask", never "run freely".
 */
interface DeclaresEffect
{
    /**
     * @param  array<string, mixed>  $config  the call's effective config, for a node whose effect depends on it (e.g. an HTTP method)
     */
    public function effect(array $config): ActionEffect;
}
