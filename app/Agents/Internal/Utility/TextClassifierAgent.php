<?php

namespace App\Agents\Internal\Utility;

use App\Agents\Internal\InternalAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

#[Temperature(0.3)]
class TextClassifierAgent extends InternalAgent implements HasStructuredOutput
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        private array $categories = [],
    ) {}

    public function instructions(): Stringable|string
    {
        $categoriesList = implode(', ', $this->categories);

        return "You are a text classifier. Classify the given text into exactly one of the following categories: {$categoriesList}. Provide your confidence score from 0 to 1.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
        ];
    }
}
