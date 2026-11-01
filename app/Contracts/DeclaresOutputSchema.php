<?php

namespace App\Contracts;

/**
 * A node whose output always has the same shape, whatever it ran against:
 * published in the node catalog as `output_schema` so the editor can offer
 * its fields before the node has ever run.
 */
interface DeclaresOutputSchema
{
    /**
     * JSON-schema-shaped description of `execute()`'s return value.
     *
     * @return array<string, mixed>
     */
    public function outputSchema(): array;
}
