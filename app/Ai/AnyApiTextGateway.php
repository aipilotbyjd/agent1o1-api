<?php

namespace App\Ai;

use Illuminate\Support\Arr;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\ObjectSchema;

/**
 * OpenRouter-compatible gateway for AnyAPI (https://api.anyapi.ai).
 *
 * AnyAPI proxies to underlying providers (OpenAI, Anthropic, …) via the
 * OpenAI-compatible /chat/completions endpoint. Those providers reject a
 * json_schema response_format with "strict": true unless every property is
 * listed in "required" — which our structured-output schemas don't guarantee.
 * The stock OpenRouter driver hardcodes strict=true, so we override the schema
 * builder here to send strict=false, which AnyAPI/OpenAI accept while still
 * steering the model toward the schema shape.
 */
class AnyApiTextGateway extends OpenRouterGateway
{
    protected function buildResponseFormat(array $schema): array
    {
        $schemaArray = (new ObjectSchema($schema))->toSchema();

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $schemaArray['name'] ?? 'schema_definition',
                'schema' => Arr::except($schemaArray, ['name']),
                'strict' => false,
            ],
        ];
    }
}
