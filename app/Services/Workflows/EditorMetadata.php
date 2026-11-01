<?php

namespace App\Services\Workflows;

/**
 * The workflow editor stores which fields are wired to upstream outputs (and
 * the values to restore when they are unwired) inside the node's `config`
 * under this key, since the graph has no other place for canvas-only state.
 * The engine never reads it, so it is removed before a config is templated,
 * scanned for secrets, or handed to `NodeContract::execute()`.
 */
final class EditorMetadata
{
    public const string KEY = '__editorInputs';

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function strip(array $config): array
    {
        unset($config[self::KEY]);

        return $config;
    }
}
