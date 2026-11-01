<?php

namespace App\Services\Workflows\NodeOptions;

use App\Exceptions\ConnectorException;
use Illuminate\Validation\ValidationException;

/**
 * Loads the choices for one or more `x-options` sources (see
 * `App\Nodes\Support\Field::dynamic()`). Registered in `NodeOptionsService`.
 */
interface OptionsSource
{
    /**
     * The `x-options.source` keys this class answers, e.g.
     * `google_sheets.spreadsheets`.
     *
     * @return list<string>
     */
    public function sources(): array;

    /**
     * @throws ConnectorException when the provider refuses or can't be reached
     * @throws ValidationException when a value the list depends on is malformed
     */
    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage;
}
