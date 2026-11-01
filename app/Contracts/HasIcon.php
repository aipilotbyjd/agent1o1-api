<?php

namespace App\Contracts;

/**
 * A node with its own icon. Nodes without one show their category's icon:
 * right for an app's nodes (every GitHub node wears the GitHub mark), not for
 * core nodes that do unrelated things under one category.
 */
interface HasIcon
{
    /**
     * A Hugeicons name in kebab-case, e.g. `route-01`.
     */
    public function icon(): string;
}
