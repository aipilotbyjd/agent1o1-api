<?php

namespace App\Nodes\Integrations\GoogleSlides;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Str;

class GoogleSlidesAddSlideNode extends AbstractGoogleSlidesNode
{
    /**
     * Layouts that have a title and a body to fill in.
     */
    private const array LAYOUTS = [
        'TITLE_AND_BODY' => 'Title and body',
        'TITLE' => 'Title slide',
        'SECTION_HEADER' => 'Section header',
        'TITLE_ONLY' => 'Title only',
        'BLANK' => 'Blank',
    ];

    public function type(): string
    {
        return 'google_slides_add_slide';
    }

    public function name(): string
    {
        return 'Google Slides: Add Slide';
    }

    public function description(): string
    {
        return 'Adds a slide at the end, with a title and body text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['presentation_id'],
            'properties' => [
                ...$this->credentialFields(),
                'presentation_id' => $this->presentationField(),
                'layout' => Field::select('Layout', self::LAYOUTS, null, 'TITLE_AND_BODY'),
                'title' => Field::text('Title', 'Ignored by the blank layout.'),
                'body' => Field::textarea('Body', 'Used by the "Title and body" layout.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $layout = array_key_exists((string) ($config['layout'] ?? ''), self::LAYOUTS) ? (string) $config['layout'] : 'TITLE_AND_BODY';
        $slideId = 'slide_'.Str::lower(Str::random(10));
        $titleId = 'title_'.Str::lower(Str::random(10));
        $bodyId = 'body_'.Str::lower(Str::random(10));

        $title = trim((string) ($config['title'] ?? ''));
        $body = trim((string) ($config['body'] ?? ''));

        // Each placeholder the layout has, with the ID the text is put into.
        $placeholders = match ($layout) {
            'TITLE_AND_BODY' => [['TITLE', $titleId, $title], ['BODY', $bodyId, $body]],
            'TITLE' => [['CENTERED_TITLE', $titleId, $title]],
            'SECTION_HEADER', 'TITLE_ONLY' => [['TITLE', $titleId, $title]],
            default => [],
        };

        $requests = [['createSlide' => [
            'objectId' => $slideId,
            'slideLayoutReference' => ['predefinedLayout' => $layout],
            'placeholderIdMappings' => array_map(fn (array $placeholder): array => [
                'layoutPlaceholder' => ['type' => $placeholder[0], 'index' => 0],
                'objectId' => $placeholder[1],
            ], $placeholders),
        ]]];

        foreach ($placeholders as [, $objectId, $text]) {
            if ($text !== '') {
                $requests[] = ['insertText' => ['objectId' => $objectId, 'text' => $text]];
            }
        }

        if ($placeholders === []) {
            unset($requests[0]['createSlide']['placeholderIdMappings']);
        }

        $this->post($run, $this->presentationPath($config).':batchUpdate', $config, ['requests' => $requests]);

        return ['presentation_id' => $config['presentation_id'], 'slide_id' => $slideId];
    }
}
