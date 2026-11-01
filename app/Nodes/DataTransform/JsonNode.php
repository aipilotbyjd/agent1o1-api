<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Turns JSON text into data and back, or reads one value out of data by path.
 * Returns `result`.
 */
class JsonNode extends PureDataNode
{
    private const array OPERATIONS = [
        'parse' => 'Read JSON text as data',
        'stringify' => 'Turn data into JSON text',
        'get' => 'Get a value by path',
    ];

    private const int MAX_JSON_BYTES = 5_000_000;

    public function type(): string
    {
        return 'json';
    }

    public function name(): string
    {
        return 'JSON';
    }

    public function icon(): string
    {
        return 'source-code';
    }

    public function description(): string
    {
        return 'Reads JSON text into data, turns data into JSON text, or gets one value from data by path such as user.emails.0.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation', 'value'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, default: 'parse'),
                // No `type`: it takes JSON text or data as it is, and a typed field would
                // turn a list from an earlier step into text before the node saw it.
                'value' => ['title' => 'Value', 'description' => 'The JSON text to read, or the data to use, usually {{ ... }} from an earlier step.', 'x-widget' => 'textarea'],
                'path' => Field::text('Path', 'Get a value: where it is, using dots, e.g. user.emails.0.', 'user.email'),
                'default' => Field::advanced(Field::text('Default', 'Get a value: returned when the path is not there. Leave empty for nothing.')),
                'pretty' => Field::advanced(Field::boolean('Pretty print', 'Turn data into JSON text: add line breaks and indents.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = (string) ($config['operation'] ?? '');
        $value = $config['value'] ?? null;

        return ['result' => match ($operation) {
            'parse' => $this->parse($value),
            'stringify' => $this->stringify($value, (bool) ($config['pretty'] ?? false)),
            'get' => $this->get($value, $config),
            default => throw new InvalidArgumentException("Unsupported JSON operation [{$operation}]."),
        }];
    }

    private function parse(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (strlen($value) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('The JSON text is too large to read (over 5 MB).');
        }

        if (trim($value) === '') {
            throw new InvalidArgumentException('There is no JSON text to read.');
        }

        try {
            return json_decode($value, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('The text is not valid JSON: '.$e->getMessage().'.');
        }
    }

    private function stringify(mixed $value, bool $pretty): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($pretty ? JSON_PRETTY_PRINT : 0);

        try {
            return json_encode($value, $flags);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('This data can\'t be written as JSON: '.$e->getMessage().'.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function get(mixed $value, array $config): mixed
    {
        $path = (string) ($config['path'] ?? '');

        if ($path === '') {
            throw new InvalidArgumentException('Getting a value needs a "path".');
        }

        $data = is_string($value) ? $this->parse($value) : $value;

        if (! is_array($data)) {
            throw new InvalidArgumentException('There is no data to look inside: the value is not a list or object.');
        }

        $default = isset($config['default']) && $config['default'] !== '' ? $config['default'] : null;

        return Arr::get($data, $path, $default);
    }
}
