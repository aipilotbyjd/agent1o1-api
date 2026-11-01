<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Masks personal data and secrets in text before it goes to a model, a log
 * or a message: pattern matching only, so it costs nothing and never sends
 * the text anywhere. It catches formats, not meaning, so a name or an address
 * in free text is not found; pair it with AI Moderate for that.
 */
class PiiRedactNode extends PureDataNode
{
    /**
     * @var array<string, string>
     */
    private const array TYPES = [
        'email' => 'Email addresses',
        'phone' => 'Phone numbers',
        'credit_card' => 'Card numbers',
        'ssn' => 'US Social Security numbers',
        'iban' => 'IBAN bank accounts',
        'ip_address' => 'IP addresses',
        'api_key' => 'API keys and tokens',
    ];

    public function type(): string
    {
        return 'pii_redact';
    }

    public function name(): string
    {
        return 'Redact Personal Data';
    }

    public function icon(): string
    {
        return 'shield-01';
    }

    public function description(): string
    {
        return 'Masks emails, phone numbers, card numbers, IDs, IP addresses and API keys in text before it goes anywhere.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
                'text' => Field::textarea('Text', 'Use {{templates}} to take it from an earlier step.'),
                'types' => Field::list('What to mask', 'Any of: '.implode(', ', array_keys(self::TYPES)).'. Leave empty to mask all of them.', 'email'),
                'style' => Field::select('Replace with', ['label' => 'A label, e.g. [EMAIL]', 'mask' => 'Asterisks'], null, 'label'),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['title' => 'Redacted text', 'description' => 'The text with the personal data masked.'],
                'counts' => ['title' => 'Counts', 'description' => 'How many of each kind were masked.'],
                'found' => ['title' => 'Found', 'description' => 'True when anything was masked.'],
                'result' => ['title' => 'Branch', 'description' => 'redacted or clean, so an edge can follow it.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $text = $this->text($config, 'text');
        $wanted = is_array($config['types'] ?? null) ? array_values(array_filter(array_map(fn (mixed $type): string => strtolower(trim((string) $type)), $config['types']))) : [];

        foreach ($wanted as $type) {
            if (! isset(self::TYPES[$type])) {
                throw new InvalidArgumentException("“{$type}” is not something this step can mask. Choose from: ".implode(', ', array_keys(self::TYPES)).'.');
            }
        }

        $wanted = $wanted === [] ? array_keys(self::TYPES) : $wanted;
        $counts = [];
        $mask = ($config['style'] ?? 'label') === 'mask';

        // Longest-and-most-specific first, so a card number is not eaten as a phone number.
        foreach (['api_key', 'iban', 'email', 'credit_card', 'ssn', 'ip_address', 'phone'] as $type) {
            if (! in_array($type, $wanted, true)) {
                continue;
            }

            $text = (string) preg_replace_callback($this->pattern($type), function (array $match) use ($type, $mask, &$counts): string {
                if ($type === 'credit_card' && ! $this->luhn($match[0])) {
                    return $match[0];
                }

                $counts[$type] = ($counts[$type] ?? 0) + 1;

                return $mask ? str_repeat('*', max(4, strlen($match[0]))) : '['.strtoupper($type === 'ip_address' ? 'IP' : $type).']';
            }, $text);
        }

        return ['text' => $text, 'counts' => $counts, 'found' => $counts !== [], 'result' => $counts === [] ? 'clean' : 'redacted'];
    }

    private function pattern(string $type): string
    {
        return match ($type) {
            'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            'phone' => '/(?<!\w)\+\d[\d\s().\-]{7,17}\d(?!\w|\.\d)|(?<!\d)(?:\(\d{3}\)\s?|\d{3}[\s.\-])\d{3}[\s.\-]\d{4}(?!\d)/',
            'credit_card' => '/(?<!\d)(?:\d[ \-]?){13,19}(?!\d)/',
            'ssn' => '/(?<!\d)\d{3}-\d{2}-\d{4}(?!\d)/',
            'iban' => '/\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]{4}){2,7}(?:[ ]?[A-Z0-9]{1,3})?\b/',
            'ip_address' => '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/',
            'api_key' => '/\b(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{10,}\b|\bgh[pousr]_[A-Za-z0-9]{30,}\b|\bAKIA[0-9A-Z]{16}\b|\bxox[abprs]-[A-Za-z0-9-]{10,}\b|\bAIza[0-9A-Za-z_\-]{35}\b|\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b/',
        };
    }

    private function luhn(string $number): bool
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        if (strlen($digits) < 13 || strlen($digits) > 19) {
            return false;
        }

        $sum = 0;

        foreach (array_reverse(str_split($digits)) as $index => $digit) {
            $value = (int) $digit * ($index % 2 === 1 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $sum % 10 === 0;
    }
}
