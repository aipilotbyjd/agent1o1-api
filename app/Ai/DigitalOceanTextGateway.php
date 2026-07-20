<?php

namespace App\Ai;

use Illuminate\Support\Arr;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\ObjectSchema;

/**
 * OpenAI-compatible gateway for DigitalOcean's Gradient AI Platform
 * serverless inference (https://inference.do-ai.run).
 *
 * Like AnyAPI, the underlying models reject a json_schema response_format
 * with "strict": true unless every property is listed in "required" — which
 * our structured-output schemas don't guarantee. The stock OpenRouter driver
 * hardcodes strict=true, so we override the schema builder to send
 * strict=false, which the OpenAI-compatible endpoint accepts.
 */
class DigitalOceanTextGateway extends OpenRouterGateway
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
