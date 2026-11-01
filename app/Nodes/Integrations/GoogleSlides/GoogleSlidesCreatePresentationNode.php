<?php

namespace App\Nodes\Integrations\GoogleSlides;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSlidesCreatePresentationNode extends AbstractGoogleSlidesNode
{
    public function type(): string
    {
        return 'google_slides_create_presentation';
    }

    public function name(): string
    {
        return 'Google Slides: Create Presentation';
    }

    public function description(): string
    {
        return 'Creates a new presentation.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title', null, 'Quarterly review'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $presentation = $this->post($run, '/presentations', $config, ['title' => $config['title']]);

        return [
            'presentation_id' => $presentation['presentationId'] ?? null,
            'title' => $presentation['title'] ?? $config['title'],
            'url' => isset($presentation['presentationId']) ? 'https://docs.google.com/presentation/d/'.$presentation['presentationId'].'/edit' : null,
        ];
    }
}
