<?php

namespace App\Services\Web;

use InvalidArgumentException;

/**
 * Turns the part of CSS selectors people actually use to pick things off a
 * page into XPath: tags, `.class`, `#id`, `[attr]`, `[attr=value]`, the
 * descendant (space) and child (`>`) combinators, and comma-separated
 * alternatives. Anything else is refused with a message, rather than quietly
 * matching the wrong thing.
 */
final class SelectorToXPath
{
    public static function convert(string $selector): string
    {
        $selector = trim($selector);

        if ($selector === '' || mb_strlen($selector) > 500) {
            throw new InvalidArgumentException('Give a CSS selector of up to 500 characters, e.g. h2.title or .product > a.');
        }

        $alternatives = [];

        foreach (preg_split('/\s*,\s*/', $selector) ?: [] as $part) {
            $alternatives[] = self::chain($part);
        }

        return implode(' | ', $alternatives);
    }

    private static function chain(string $selector): string
    {
        $tokens = preg_split('/\s*(>)\s*|\s+/', trim($selector), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $xpath = '';
        $child = false;

        foreach ($tokens as $token) {
            if ($token === '>') {
                $child = true;

                continue;
            }

            $xpath .= ($xpath === '' ? '//' : ($child ? '/' : '//')).self::compound($token);
            $child = false;
        }

        if ($xpath === '') {
            throw new InvalidArgumentException("“{$selector}” is not a selector this step understands.");
        }

        return $xpath;
    }

    private static function compound(string $token): string
    {
        $tag = '*';
        $conditions = [];

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9-]*/', $token, $match) === 1) {
            $tag = strtolower($match[0]);
            $token = substr($token, strlen($match[0]));
        }

        while ($token !== '') {
            if (preg_match('/^#([\w-]+)/', $token, $m) === 1) {
                $conditions[] = '@id='.self::literal($m[1]);
            } elseif (preg_match('/^\.([\w-]+)/', $token, $m) === 1) {
                $conditions[] = 'contains(concat(" ", normalize-space(@class), " "), '.self::literal(' '.$m[1].' ').')';
            } elseif (preg_match('/^\[([\w:-]+)(?:(\^=|\$=|\*=|=)(?:"([^"]*)"|\'([^\']*)\'|([^\]]*)))?\]/', $token, $m) === 1) {
                $attribute = '@'.$m[1];
                $value = ($m[3] ?? '') !== '' ? $m[3] : (($m[4] ?? '') !== '' ? $m[4] : ($m[5] ?? ''));

                $conditions[] = match ($m[2] ?? '') {
                    '' => $attribute,
                    '=' => $attribute.'='.self::literal($value),
                    '^=' => 'starts-with('.$attribute.', '.self::literal($value).')',
                    '$=' => 'substring('.$attribute.', string-length('.$attribute.') - '.(mb_strlen($value) - 1).') = '.self::literal($value),
                    default => 'contains('.$attribute.', '.self::literal($value).')',
                };
            } else {
                throw new InvalidArgumentException("The selector part “{$token}” is not supported. Use tags, .classes, #ids and [attributes], with a space or > between them.");
            }

            $token = substr($token, strlen($m[0]));
        }

        return $tag.($conditions === [] ? '' : '['.implode(' and ', $conditions).']');
    }

    private static function literal(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return "\"{$value}\"";
        }

        return 'concat(\''.implode('\', "\'", \'', explode("'", $value)).'\')';
    }
}
