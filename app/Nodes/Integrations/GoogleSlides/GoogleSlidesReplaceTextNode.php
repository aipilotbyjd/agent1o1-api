<?php

namespace App\Nodes\Integrations\GoogleSlides;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleSlidesReplaceTextNode extends AbstractGoogleSlidesNode
{
    public function type(): string
    {
        return 'google_slides_replace_text';
    }

    public function name(): string
    {
        return 'Google Slides: Replace Text';
    }

    public function description(): string
    {
        return 'Replaces every occurrence of some text, such as {{name}} placeholders in a template.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['presentation_id', 'replacements'],
            'properties' => [
                ...$this->credentialFields(),
                'presentation_id' => $this->presentationField(),
                'replacements' => Field::keyValue('Replacements', 'Each row replaces the text on the left with the text on the right, e.g. {{name}} → Ada.'),
                'match_case' => Field::boolean('Match upper and lower case', null, true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $requests = [];

        foreach ((array) $config['replacements'] as $find => $replacement) {
            if ((string) $find === '') {
                continue;
            }

            $requests[] = ['replaceAllText' => [
                'containsText' => ['text' => (string) $find, 'matchCase' => (bool) ($config['match_case'] ?? true)],
                'replaceText' => (string) $replacement,
            ]];
        }

        if ($requests === []) {
            throw new InvalidArgumentException('Google Slides: add at least one text to replace.');
        }

        $response = $this->post($run, $this->presentationPath($config).':batchUpdate', $config, ['requests' => $requests]);

        return [
            'presentation_id' => $response['presentationId'] ?? $config['presentation_id'],
            'occurrences_changed' => array_sum(array_map(
                fn (array $reply): int => (int) ($reply['replaceAllText']['occurrencesChanged'] ?? 0),
                (array) ($response['replies'] ?? []),
            )),
        ];
    }
}
