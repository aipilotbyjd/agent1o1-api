<?php

namespace App\Nodes\Integrations\GoogleSlides;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleSlidesGetPresentationNode extends AbstractGoogleSlidesNode
{
    public function type(): string
    {
        return 'google_slides_get_presentation';
    }

    public function name(): string
    {
        return 'Google Slides: Get Presentation';
    }

    public function description(): string
    {
        return 'Reads a presentation: its title and the text on each slide.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['presentation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'presentation_id' => $this->presentationField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $presentation = $this->get($run, $this->presentationPath($config), $config);

        $slides = [];

        foreach ((array) ($presentation['slides'] ?? []) as $index => $slide) {
            $slides[] = [
                'number' => $index + 1,
                'slide_id' => $slide['objectId'] ?? null,
                'text' => trim($this->textOf((array) ($slide['pageElements'] ?? []))),
            ];
        }

        return [
            'presentation_id' => $presentation['presentationId'] ?? $config['presentation_id'],
            'title' => $presentation['title'] ?? null,
            'slide_count' => count($slides),
            'slides' => $slides,
        ];
    }

    /**
     * The text in a slide's shapes and tables, one line per text box.
     *
     * @param  list<array<string, mixed>>  $elements
     */
    private function textOf(array $elements): string
    {
        $lines = [];

        foreach ($elements as $element) {
            $runs = (array) ($element['shape']['text']['textElements'] ?? []);

            foreach ((array) ($element['table']['tableRows'] ?? []) as $row) {
                foreach ((array) ($row['tableCells'] ?? []) as $cell) {
                    array_push($runs, ...(array) ($cell['text']['textElements'] ?? []));
                }
            }

            $text = trim(implode('', array_map(fn (array $run): string => (string) ($run['textRun']['content'] ?? ''), $runs)));

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }
}
