<?php

namespace App\Agents\Internal;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Stringable;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class StructuredExtractAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private array $schemaProperties
    ) {}

    public function instructions(): Stringable|string
    {
        return 'You are a data extraction expert. Parse the provided text and extract the required information accurately based on the given schema requirements.';
    }

    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->schemaProperties as $key => $typeConfig) {
            $type = $typeConfig['type'] ?? 'string';

            if ($type === 'string') {
                $prop = $schema->string();
            } elseif ($type === 'number' || $type === 'integer') {
                $prop = $schema->number();
            } elseif ($type === 'boolean') {
                $prop = $schema->boolean();
            } elseif ($type === 'array') {
                $prop = $schema->array();
                if (isset($typeConfig['items'])) {
                    if (($typeConfig['items']['type'] ?? '') === 'string') {
                        $prop->items($schema->string());
                    } elseif (($typeConfig['items']['type'] ?? '') === 'number') {
                        $prop->items($schema->number());
                    }
                }
            } else {
                $prop = $schema->string();
            }

            if (! empty($typeConfig['description'])) {
                $prop->description($typeConfig['description']);
            }

            $properties[$key] = $prop;
        }

        return $properties;
    }
}
