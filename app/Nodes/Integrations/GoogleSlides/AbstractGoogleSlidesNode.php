<?php

namespace App\Nodes\Integrations\GoogleSlides;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;
use App\Nodes\Support\Field;

/**
 * Shared request handling for the Google Slides node family.
 */
abstract class AbstractGoogleSlidesNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_slides';
    }

    protected function baseUrl(): string
    {
        return 'https://slides.googleapis.com/v1';
    }

    protected function appName(): string
    {
        return 'Google Slides';
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentationField(): array
    {
        return Field::dynamic('Presentation', 'google_slides.presentations', 'Pick a presentation, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp');
    }

    /**
     * `/presentations/{id}`, with the ID URL-encoded.
     *
     * @param  array<string, mixed>  $config
     */
    protected function presentationPath(array $config): string
    {
        return '/presentations/'.rawurlencode((string) $config['presentation_id']);
    }
}
