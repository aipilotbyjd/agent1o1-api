<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Workflows\ConditionEvaluator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One text operation per step: clean, change case, find and replace, cut,
 * split, or pull a piece out with a pattern. Returns `result`.
 */
class StringNode extends PureDataNode
{
    private const array OPERATIONS = [
        'trim' => 'Trim spaces',
        'uppercase' => 'UPPERCASE',
        'lowercase' => 'lowercase',
        'title_case' => 'Title Case',
        'slugify' => 'Make a URL slug',
        'length' => 'Count characters',
        'replace' => 'Find and replace',
        'regex_replace' => 'Replace with a pattern (regex)',
        'regex_extract' => 'Extract with a pattern (regex)',
        'substring' => 'Take part of the text',
        'truncate' => 'Shorten with an ending',
        'split' => 'Split into a list',
    ];

    private const int MAX_TEXT_LENGTH = 1_000_000;

    public function type(): string
    {
        return 'string';
    }

    public function name(): string
    {
        return 'Text';
    }

    public function icon(): string
    {
        return 'text';
    }

    public function description(): string
    {
        return 'Cleans and reshapes text: trim, change case, find and replace, patterns, cut, shorten, split, count.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation', 'text'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, default: 'trim'),
                'text' => Field::textarea('Text', 'The text to work on, usually {{ ... }} from an earlier step.'),
                'search' => Field::text('Find', 'Find and replace: the text to look for.'),
                'pattern' => Field::text('Pattern', 'Pattern operations: a regular expression without slashes, e.g. ORD-(\d+). Up to 200 characters.', 'ORD-(\d+)'),
                'replacement' => Field::text('Replace with', 'Find and replace, and pattern replace. Pattern replace can use $1 for the first group.'),
                'start' => Field::integer('Start at', 'Take part of the text: the first character to keep. 0 is the first; a negative number counts from the end.', 0),
                'length' => Field::integer('Length', 'Take part of the text / shorten: how many characters. Leave empty to take the rest.', null, 0, 100000),
                'ending' => Field::text('Ending', 'Shorten: added when the text was cut.', '...'),
                'separator' => Field::text('Separator', 'Split: where to cut the text, e.g. a comma.', ','),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = (string) ($config['operation'] ?? '');
        $text = $this->text($config, 'text');

        if (! array_key_exists($operation, self::OPERATIONS)) {
            throw new InvalidArgumentException("Unsupported text operation [{$operation}].");
        }

        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException('The text is too long to process (over 1,000,000 characters).');
        }

        return ['result' => match ($operation) {
            'trim' => trim($text),
            'uppercase' => Str::upper($text),
            'lowercase' => Str::lower($text),
            'title_case' => Str::title($text),
            'slugify' => Str::slug($text),
            'length' => mb_strlen($text),
            'replace' => str_replace($this->required($config, 'search'), $this->text($config, 'replacement'), $text),
            'regex_replace' => $this->regexReplace($config, $text),
            'regex_extract' => $this->regexExtract($config, $text),
            'substring' => mb_substr($text, (int) ($config['start'] ?? 0), isset($config['length']) && $config['length'] !== '' ? (int) $config['length'] : null),
            'truncate' => Str::limit($text, max(0, (int) ($config['length'] ?? 100)), array_key_exists('ending', $config) ? $this->text($config, 'ending') : '...'),
            'split' => explode($this->required($config, 'separator'), $text),
        }];
    }

    /**
     * A setting an operation can't work without.
     *
     * @param  array<string, mixed>  $config
     */
    private function required(array $config, string $key): string
    {
        $value = $this->text($config, $key);

        if ($value === '') {
            throw new InvalidArgumentException("This operation needs \"{$key}\" to be filled in.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function pattern(array $config): string
    {
        $pattern = $this->text($config, 'pattern');

        if (! ConditionEvaluator::isValidPattern($pattern)) {
            throw new InvalidArgumentException('The pattern is empty, over 200 characters, or not a valid regular expression.');
        }

        return ConditionEvaluator::regex($pattern);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function regexReplace(array $config, string $text): string
    {
        $result = @preg_replace($this->pattern($config), $this->text($config, 'replacement'), $text);

        if ($result === null) {
            throw new InvalidArgumentException('The pattern could not be applied to this text.');
        }

        return $result;
    }

    /**
     * The first group of the first match, or the whole match when the pattern
     * has no group. Null when nothing matches.
     *
     * @param  array<string, mixed>  $config
     */
    private function regexExtract(array $config, string $text): ?string
    {
        $found = @preg_match($this->pattern($config), $text, $matches);

        if ($found === false) {
            throw new InvalidArgumentException('The pattern could not be applied to this text.');
        }

        return $found === 1 ? ($matches[1] ?? $matches[0]) : null;
    }
}
