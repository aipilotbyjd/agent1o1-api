<?php

namespace App\Nodes\DataTransform;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\NodeCategory;
use InvalidArgumentException;

/**
 * Base for the data nodes that only reshape what they are given: no network,
 * no files, no database. They read their config (already resolved from
 * `{{ }}` templates by the engine), return a value, and change nothing
 * outside the run, so an agent may call them freely.
 *
 * A bad input is an `InvalidArgumentException` with a sentence a person can
 * act on: the step fails and the run log shows it.
 */
abstract class PureDataNode implements DeclaresEffect, DeclaresOutputSchema, HasIcon, NodeContract
{
    /**
     * Every pure data node lives in Data & Transform.
     */
    public function category(): string
    {
        return NodeCategory::DataTransform->value;
    }

    /**
     * Reshaping data changes nothing outside the run.
     */
    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    /**
     * The one output key most of these nodes return.
     *
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['title' => 'Result', 'description' => 'The value this step produced.'],
            ],
        ];
    }

    /**
     * A config value that must be text (or a number, read as text).
     *
     * @param  array<string, mixed>  $config
     */
    protected function text(array $config, string $key): string
    {
        $value = $config[$key] ?? '';

        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException("\"{$key}\" must be text, but a list or object was given.");
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * A config value that must be a number.
     *
     * @param  array<string, mixed>  $config
     */
    protected function number(array $config, string $key): int|float
    {
        $value = $config[$key] ?? null;

        if (! is_numeric($value) || is_bool($value)) {
            throw new InvalidArgumentException("\"{$key}\" must be a number.");
        }

        return $value + 0;
    }
}
