<?php

namespace App\Services\Workflows;

/**
 * The `{{ }}` templating syntax's entire security boundary: the captured
 * expression may only contain word characters, dots, and `[n]` array
 * indices: no operators, quotes, or function calls. This isn't a general
 * expression language (deliberately, mirroring `RunCodeNode`'s own
 * restraint (see docs/NODES_CATALOG.md)) it's a path into the run's
 * templating context (`{{ input.name }}`, `{{ nodes.a.result }}`), nothing
 * more. Anything outside this charset simply never matches, so it's left in
 * the output as literal text rather than evaluated.
 */
final class SafePattern
{
    /**
     * The entire config value is exactly one expression: resolves to the
     * raw typed value (array, number, bool, ...), not a string.
     */
    public const string WHOLE = '/^\s*\{\{\s*([a-zA-Z0-9_.\[\]]+)\s*\}\}\s*$/';

    /**
     * One or more expressions embedded inside a larger string: each
     * resolved value is stringified and substituted in place.
     */
    public const string EMBEDDED = '/\{\{\s*([a-zA-Z0-9_.\[\]]*)\s*\}\}/';
}
