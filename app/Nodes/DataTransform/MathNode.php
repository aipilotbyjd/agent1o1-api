<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * One calculation per step: arithmetic on two numbers, rounding on one, or
 * sum, average, smallest and largest over a list. Returns `result`.
 */
class MathNode extends PureDataNode
{
    private const array OPERATIONS = [
        'add' => 'Add (A + B)',
        'subtract' => 'Subtract (A - B)',
        'multiply' => 'Multiply (A x B)',
        'divide' => 'Divide (A / B)',
        'modulo' => 'Remainder (A mod B)',
        'power' => 'Power (A to the B)',
        'round' => 'Round A',
        'floor' => 'Round A down',
        'ceil' => 'Round A up',
        'abs' => 'Absolute value of A',
        'sum' => 'Sum of a list',
        'average' => 'Average of a list',
        'min' => 'Smallest in a list',
        'max' => 'Largest in a list',
    ];

    private const array NEEDS_B = ['add', 'subtract', 'multiply', 'divide', 'modulo', 'power'];

    private const array NEEDS_LIST = ['sum', 'average', 'min', 'max'];

    private const int MAX_LIST = 100000;

    public function type(): string
    {
        return 'math';
    }

    public function name(): string
    {
        return 'Math';
    }

    public function icon(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Calculates: add, subtract, multiply, divide, remainder, power, rounding, and sum, average, smallest or largest of a list.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation'],
            'properties' => [
                'operation' => Field::select('Operation', self::OPERATIONS, default: 'add'),
                'a' => Field::text('A', 'The first number, usually {{ ... }} from an earlier step.', '10'),
                'b' => Field::text('B', 'The second number, for add, subtract, multiply, divide, remainder and power.', '2'),
                'values' => Field::list('Numbers', 'Sum, average, smallest and largest: the numbers to use.', '10'),
                'decimals' => Field::integer('Decimal places', 'Round A: how many decimals to keep. 0 rounds to a whole number.', 0, 0, 10),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = (string) ($config['operation'] ?? '');

        if (! array_key_exists($operation, self::OPERATIONS)) {
            throw new InvalidArgumentException("Unsupported math operation [{$operation}].");
        }

        if (in_array($operation, self::NEEDS_LIST, true)) {
            return ['result' => $this->aggregate($operation, $this->numbers($config['values'] ?? null))];
        }

        $a = $this->number($config, 'a');
        $b = in_array($operation, self::NEEDS_B, true) ? $this->number($config, 'b') : 0;

        return ['result' => $this->finite(match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b == 0 ? throw new InvalidArgumentException('Can\'t divide by zero.') : $a / $b,
            'modulo' => $b == 0 ? throw new InvalidArgumentException('Can\'t take a remainder by zero.') : fmod($a, $b),
            'power' => $a ** $b,
            'round' => round($a, max(0, min(10, (int) ($config['decimals'] ?? 0)))),
            'floor' => floor($a),
            'ceil' => ceil($a),
            'abs' => abs($a),
        })];
    }

    /**
     * @return list<int|float>
     */
    private function numbers(mixed $values): array
    {
        if (! is_array($values) || $values === []) {
            throw new InvalidArgumentException('Give at least one number in "values".');
        }

        if (count($values) > self::MAX_LIST) {
            throw new InvalidArgumentException('The list is too long to calculate (over 100,000 numbers).');
        }

        return array_map(function (mixed $value): int|float {
            if (! is_numeric($value) || is_bool($value)) {
                $shown = is_scalar($value) ? (string) $value : gettype($value);

                throw new InvalidArgumentException("\"{$shown}\" in \"values\" is not a number.");
            }

            return $value + 0;
        }, array_values($values));
    }

    /**
     * @param  list<int|float>  $numbers
     */
    private function aggregate(string $operation, array $numbers): int|float
    {
        return $this->finite(match ($operation) {
            'sum' => array_sum($numbers),
            'average' => array_sum($numbers) / count($numbers),
            'min' => min($numbers),
            'max' => max($numbers),
        });
    }

    /**
     * Rounding and whole-number results come back as a whole number, and a
     * result too large to hold is an error rather than INF in the output.
     */
    private function finite(int|float $result): int|float
    {
        if (is_float($result) && ! is_finite($result)) {
            throw new InvalidArgumentException('The result is too large to hold.');
        }

        return is_float($result) && $result == floor($result) && abs($result) < PHP_INT_MAX ? (int) $result : $result;
    }
}
