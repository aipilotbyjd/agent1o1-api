<?php

namespace App\Engine\Graph;

use Carbon\Carbon;
use Illuminate\Support\Str;

class ExpressionResolver
{
    private const EXPR_PATTERN = '/\{\{\s*(.+?)\s*\}\}/';

    public function compile(string $template): array
    {
        if (! str_contains($template, '{{')) {
            return [['type' => 'literal', 'value' => $template]];
        }

        $tokens = [];
        $offset = 0;

        preg_match_all(self::EXPR_PATTERN, $template, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $i => $match) {
            [$fullMatch, $matchOffset] = $match;
            $expression = $matches[1][$i][0];

            if ($matchOffset > $offset) {
                $tokens[] = ['type' => 'literal', 'value' => substr($template, $offset, $matchOffset - $offset)];
            }

            $tokens[] = ['type' => 'expression', 'value' => trim($expression)];
            $offset = $matchOffset + strlen($fullMatch);
        }

        if ($offset < strlen($template)) {
            $tokens[] = ['type' => 'literal', 'value' => substr($template, $offset)];
        }

        return $tokens;
    }

    public function resolve(array $tokens, array $context): mixed
    {
        $results = [];

        foreach ($tokens as $token) {
            $results[] = $token['type'] === 'expression'
                ? $this->evaluateExpression($token['value'], $context)
                : $token['value'];
        }

        if (count($results) === 1) {
            return $results[0];
        }

        return implode('', array_map(
            fn ($v) => is_array($v) ? json_encode($v) : (string) $v,
            $results,
        ));
    }

    public function evaluate(string $template, array $context): mixed
    {
        return $this->resolve($this->compile($template), $context);
    }

    public function compileConfig(array $config): array
    {
        return $this->walkConfig($config, fn (string $value) => $this->compile($value));
    }

    public function resolveConfig(array $compiledConfig, array $context): array
    {
        $result = [];

        foreach ($compiledConfig as $key => $value) {
            if ($this->isTokenList($value)) {
                $result[$key] = $this->resolve($value, $context);
            } elseif (is_array($value)) {
                $result[$key] = $this->resolveConfig($value, $context);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isTokenList(mixed $value): bool
    {
        return is_array($value)
            && isset($value[0]['type'], $value[0]['value'])
            && in_array($value[0]['type'], ['literal', 'expression'], true);
    }

    public function hasExpressions(array|string $config): bool
    {
        if (is_string($config)) {
            return str_contains($config, '{{');
        }

        return (bool) preg_grep('/\{\{/', array_map(
            fn ($v) => is_string($v) ? $v : '',
            $this->flatten($config),
        ));
    }

    public function extractNodeDependencies(string $template): array
    {
        preg_match_all('/\{\{\s*nodes\.(\w+)\./', $template, $matches);

        return array_unique($matches[1]);
    }

    private function evaluateExpression(string $expression, array $context): mixed
    {
        // Quoted string literal: 'value' or "value"
        if (preg_match('/^([\'"])(.*)\1$/s', $expression, $m)) {
            return $m[2];
        }

        // Numeric literal
        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        // Boolean / null literals
        if ($expression === 'true') {
            return true;
        }
        if ($expression === 'false') {
            return false;
        }
        if ($expression === 'null') {
            return null;
        }

        // Function call: fn(arg1, arg2)
        if (preg_match('/^(\w+[\w.]*)\((.*)?\)$/s', $expression, $m)) {
            return $this->callFunction($m[1], $m[2], $context);
        }

        // Path access: nodes.name.output.field or variables.key or trigger.field
        return $this->resolvePath($expression, $context);
    }

    private function resolvePath(string $path, array $context): mixed
    {
        $parts = explode('.', $path);
        $current = $context;

        foreach ($parts as $part) {
            if (is_array($current)) {
                $current = $current[$part] ?? null;
            } elseif (is_object($current)) {
                $current = $current->{$part} ?? null;
            } else {
                return null;
            }
        }

        return $current;
    }

    private function callFunction(string $name, string $argsRaw, array $context): mixed
    {
        $args = $this->parseArgs($argsRaw, $context);

        return match ($name) {
            // Date functions
            'now' => Carbon::now()->toISOString(),
            'dates.now' => Carbon::now()->toISOString(),
            'dates.format' => isset($args[0]) ? Carbon::parse($args[0])->format($args[1] ?? 'Y-m-d') : null,
            'dates.add' => isset($args[0]) ? Carbon::parse($args[0])->add($args[2] ?? 'days', (int) ($args[1] ?? 1))->toISOString() : null,
            'dates.subtract' => isset($args[0]) ? Carbon::parse($args[0])->sub($args[2] ?? 'days', (int) ($args[1] ?? 1))->toISOString() : null,
            'dates.parse' => isset($args[0]) ? Carbon::parse($args[0])->toISOString() : null,
            'dates.diff' => isset($args[0], $args[1]) ? Carbon::parse($args[0])->diffInSeconds(Carbon::parse($args[1])) : null,
            // JSON functions
            'json.parse' => isset($args[0]) ? json_decode($args[0], true) : null,
            'json.stringify' => isset($args[0]) ? json_encode($args[0]) : null,
            'json.extract' => isset($args[0], $args[1]) ? data_get($args[0], $args[1]) : null,
            'json.merge' => isset($args[0], $args[1]) ? array_merge((array) $args[0], (array) $args[1]) : null,
            // Array functions
            'arrays.map' => isset($args[0]) ? array_map(fn ($v) => $v, (array) $args[0]) : null,
            'arrays.filter' => isset($args[0]) ? array_values(array_filter((array) $args[0])) : null,
            'arrays.pluck' => isset($args[0], $args[1]) ? array_column((array) $args[0], $args[1]) : null,
            'arrays.flatten' => isset($args[0]) ? $this->flatten((array) $args[0]) : null,
            'arrays.join' => isset($args[0]) ? implode($args[1] ?? ',', (array) $args[0]) : null,
            'arrays.slice' => isset($args[0]) ? array_slice((array) $args[0], (int) ($args[1] ?? 0), isset($args[2]) ? (int) $args[2] : null) : null,
            'arrays.reverse' => isset($args[0]) ? array_reverse((array) $args[0]) : null,
            'arrays.length', 'arrays.count' => isset($args[0]) ? count((array) $args[0]) : 0,
            'arrays.first' => isset($args[0]) ? (array_values((array) $args[0])[0] ?? null) : null,
            'arrays.last' => isset($args[0]) ? (array_values(array_reverse((array) $args[0]))[0] ?? null) : null,
            'arrays.unique' => isset($args[0]) ? array_values(array_unique((array) $args[0])) : null,
            // Math functions
            'math.sum', 'sum' => isset($args[0]) ? array_sum((array) $args[0]) : array_sum($args),
            'math.avg', 'avg' => isset($args[0]) ? (array_sum((array) $args[0]) / max(count((array) $args[0]), 1)) : null,
            'math.min', 'min' => isset($args[0]) && is_array($args[0]) ? min($args[0]) : (count($args) > 0 ? min($args) : null),
            'math.max', 'max' => isset($args[0]) && is_array($args[0]) ? max($args[0]) : (count($args) > 0 ? max($args) : null),
            'math.round', 'round' => isset($args[0]) ? round((float) $args[0], (int) ($args[1] ?? 0)) : null,
            'math.floor', 'floor' => isset($args[0]) ? floor((float) $args[0]) : null,
            'math.ceil', 'ceil' => isset($args[0]) ? ceil((float) $args[0]) : null,
            'math.abs', 'abs' => isset($args[0]) ? abs((float) $args[0]) : null,
            // String functions
            'strings.uppercase', 'uppercase' => isset($args[0]) ? strtoupper((string) $args[0]) : null,
            'strings.lowercase', 'lowercase' => isset($args[0]) ? strtolower((string) $args[0]) : null,
            'strings.trim', 'trim' => isset($args[0]) ? trim((string) $args[0]) : null,
            'strings.split', 'split' => isset($args[0]) ? explode($args[1] ?? ',', (string) $args[0]) : null,
            'strings.contains', 'contains' => isset($args[0], $args[1]) ? str_contains((string) $args[0], (string) $args[1]) : false,
            'strings.replace', 'replace' => isset($args[0], $args[1], $args[2]) ? str_replace((string) $args[1], (string) $args[2], (string) $args[0]) : null,
            'strings.length', 'strlen' => isset($args[0]) ? strlen((string) $args[0]) : 0,
            'strings.startsWith' => isset($args[0], $args[1]) ? str_starts_with((string) $args[0], (string) $args[1]) : false,
            'strings.endsWith' => isset($args[0], $args[1]) ? str_ends_with((string) $args[0], (string) $args[1]) : false,
            'strings.slug' => isset($args[0]) ? Str::slug((string) $args[0]) : null,
            'strings.uuid' => Str::uuid()->toString(),
            // Logic
            'if' => isset($args[0]) ? ($args[0] ? ($args[1] ?? null) : ($args[2] ?? null)) : null,
            'and' => count($args) >= 2 && $args[0] && $args[1],
            'or' => count($args) >= 2 && ($args[0] || $args[1]),
            'not' => isset($args[0]) && ! $args[0],
            // Type
            'typeof' => isset($args[0]) ? gettype($args[0]) : 'null',
            'int', 'integer' => isset($args[0]) ? (int) $args[0] : null,
            'float' => isset($args[0]) ? (float) $args[0] : null,
            'string' => isset($args[0]) ? (string) $args[0] : null,
            'bool', 'boolean' => isset($args[0]) ? (bool) $args[0] : null,
            default => null,
        };
    }

    private function parseArgs(string $raw, array $context): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $args = [];
        $depth = 0;
        $current = '';
        $inString = null; // null | '\'' | '"'

        for ($i = 0, $len = strlen($raw); $i < $len; $i++) {
            $char = $raw[$i];

            if ($inString !== null) {
                $current .= $char;
                // End of string — unescaped closing quote
                if ($char === $inString && ($i === 0 || $raw[$i - 1] !== '\\')) {
                    $inString = null;
                }
            } elseif ($char === '\'' || $char === '"') {
                $inString = $char;
                $current .= $char;
            } elseif (in_array($char, ['(', '[', '{'], true)) {
                $depth++;
                $current .= $char;
            } elseif (in_array($char, [')', ']', '}'], true)) {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $args[] = $this->evaluateExpression(trim($current), $context);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $args[] = $this->evaluateExpression(trim($current), $context);
        }

        return $args;
    }

    private function walkConfig(array $config, callable $fn): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->walkConfig($value, $fn);
            } elseif (is_string($value)) {
                $result[$key] = $fn($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function flatten(array $array, float $depth = INF): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                $result = array_merge($result, $this->flatten($item, $depth - 1));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }
}
