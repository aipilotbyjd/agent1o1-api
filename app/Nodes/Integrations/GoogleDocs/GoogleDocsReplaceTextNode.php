<?php

namespace App\Nodes\Integrations\GoogleDocs;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleDocsReplaceTextNode extends AbstractGoogleDocsNode
{
    public function type(): string
    {
        return 'google_docs_replace_text';
    }

    public function name(): string
    {
        return 'Google Docs: Replace Text';
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
            'required' => ['document_id', 'replacements'],
            'properties' => [
                ...$this->credentialFields(),
                'document_id' => Field::dynamic('Document', 'google_docs.documents', 'Pick a document, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
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
            throw new InvalidArgumentException('Google Docs: add at least one text to replace.');
        }

        $response = $this->post($run, '/documents/'.rawurlencode((string) $config['document_id']).':batchUpdate', $config, ['requests' => $requests]);

        return [
            'document_id' => $response['documentId'] ?? $config['document_id'],
            'occurrences_changed' => array_sum(array_map(
                fn (array $reply): int => (int) ($reply['replaceAllText']['occurrencesChanged'] ?? 0),
                (array) ($response['replies'] ?? []),
            )),
        ];
    }
}
