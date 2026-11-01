<?php

namespace App\Nodes\Support;

use Illuminate\Support\Str;

/**
 * Builders for the entries of a node's `configSchema()['properties']`. Each
 * returns plain JSON-schema (`type`, `title`, `description`, `enum`,
 * `default`, `format`, `items`) (what `ConfigSchemaValidator` and agent
 * tool schemas read) plus `x-` keys that only the workflow editor reads to
 * render a real form instead of a column of text boxes:
 *
 * - `x-widget`: which input to draw; `text`, `textarea`, `number`,
 *   `toggle`, `select`, `datetime`, `emails`, `json`, `key-value`, `list`,
 *   `credential` (an account picker for `x-connector`).
 * - `x-placeholder`: example value shown in an empty input.
 * - `x-enum-labels`: `{value: label}` for a `select` with fixed choices.
 * - `x-options`: a dropdown filled at edit time from the connected account
 *   (or the workspace) via `POST /workspaces/{workspace}/nodes/options`:
 *   `{source, depends_on, uses, multiple, searchable, allow_custom}`. A
 *   field listed in `depends_on` must be set before this one can load
 *   (e.g. the sheet tab list needs a spreadsheet); one in `uses` narrows
 *   the list when set but isn't required (e.g. the calendar to list
 *   events from, primary by default). `allow_custom` keeps a free-text /
 *   `{{template}}` escape hatch next to the list. `multiple` on a string
 *   field means a comma-joined value.
 * - `x-hidden`: never drawn (legacy fields still honoured at run time).
 * - `x-advanced`: drawn under a collapsed "More options" section.
 *
 * Properties are drawn in declaration order.
 */
final class Field
{
    /**
     * @return array<string, mixed>
     */
    public static function text(string $title, ?string $description = null, ?string $placeholder = null): array
    {
        return self::make(['type' => 'string', 'x-widget' => 'text'], $title, $description, $placeholder);
    }

    /**
     * @return array<string, mixed>
     */
    public static function textarea(string $title, ?string $description = null, ?string $placeholder = null): array
    {
        return self::make(['type' => 'string', 'x-widget' => 'textarea'], $title, $description, $placeholder);
    }

    /**
     * Comma-separated email addresses.
     *
     * @return array<string, mixed>
     */
    public static function emails(string $title, ?string $description = null, ?string $placeholder = 'name@example.com'): array
    {
        return self::make(['type' => 'string', 'x-widget' => 'emails'], $title, $description ?? 'Separate multiple addresses with commas.', $placeholder);
    }

    /**
     * An ISO-8601 timestamp.
     *
     * @return array<string, mixed>
     */
    public static function datetime(string $title, ?string $description = null): array
    {
        return self::make(['type' => 'string', 'format' => 'date-time', 'x-widget' => 'datetime'], $title, $description, '2026-01-31T09:00:00Z');
    }

    /**
     * @return array<string, mixed>
     */
    public static function integer(string $title, ?string $description = null, ?int $default = null, ?int $minimum = null, ?int $maximum = null): array
    {
        return array_filter(
            self::make(['type' => 'integer', 'x-widget' => 'number', 'default' => $default, 'minimum' => $minimum, 'maximum' => $maximum], $title, $description),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function boolean(string $title, ?string $description = null, bool $default = false): array
    {
        return self::make(['type' => 'boolean', 'x-widget' => 'toggle', 'default' => $default], $title, $description);
    }

    /**
     * A fixed list of choices: a `value => label` map, or a plain list of
     * values labelled by headline-casing them (`not_equals` → "Not Equals").
     *
     * @param  array<string, string>|list<string>  $choices
     * @return array<string, mixed>
     */
    public static function select(string $title, array $choices, ?string $description = null, ?string $default = null): array
    {
        if (array_is_list($choices)) {
            $choices = array_combine($choices, array_map(fn (string $value): string => Str::headline($value), $choices));
        }

        return array_filter(
            self::make([
                'type' => 'string',
                'enum' => array_map('strval', array_keys($choices)),
                'x-enum-labels' => $choices,
                'x-widget' => 'select',
                'default' => $default,
            ], $title, $description),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * A dropdown whose choices are loaded live from `$source`: see the class
     * docblock's `x-options`.
     *
     * @param  list<string>  $dependsOn
     * @param  'string'|'integer'|'array'  $type  the saved value's shape; `array` holds several ids
     * @param  list<string>  $uses
     * @return array<string, mixed>
     */
    public static function dynamic(
        string $title,
        string $source,
        ?string $description = null,
        ?string $placeholder = null,
        array $dependsOn = [],
        bool $multiple = false,
        bool $allowCustom = true,
        string $type = 'string',
        array $uses = [],
    ): array {
        $field = self::make([
            'type' => $type,
            'x-widget' => 'select',
            'x-options' => [
                'source' => $source,
                'depends_on' => $dependsOn,
                'uses' => $uses,
                'multiple' => $multiple,
                'searchable' => true,
                'allow_custom' => $allowCustom,
            ],
        ], $title, $description, $placeholder);

        if ($type === 'array') {
            $field['items'] = ['type' => 'string'];
        }

        return $field;
    }

    /**
     * A free-form list of strings.
     *
     * @return array<string, mixed>
     */
    public static function list(string $title, ?string $description = null, ?string $placeholder = null): array
    {
        return self::make(['type' => 'array', 'items' => ['type' => 'string'], 'x-widget' => 'list'], $title, $description, $placeholder);
    }

    /**
     * A JSON value: `object` or `array`.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function json(string $title, ?string $description = null, string $type = 'object', array $extra = []): array
    {
        return self::make(['type' => $type, 'x-widget' => 'json', ...$extra], $title, $description);
    }

    /**
     * A flat `{key: value}` object, drawn as key/value rows.
     *
     * @return array<string, mixed>
     */
    public static function keyValue(string $title, ?string $description = null): array
    {
        return self::make(['type' => 'object', 'x-widget' => 'key-value'], $title, $description);
    }

    /**
     * The connected-account picker for `$connectorKey`; left empty, the
     * node uses the member's (or the workspace's) default account.
     *
     * @return array<string, mixed>
     */
    public static function credential(string $connectorKey): array
    {
        return self::make(
            ['type' => 'string', 'x-widget' => 'credential', 'x-connector' => $connectorKey],
            'Account',
            'The connected account to use. Leave empty to use your default account.',
        );
    }

    /**
     * Accepted by the node, but never drawn by the editor.
     *
     * @return array<string, mixed>
     */
    public static function hidden(?string $description = null, string $type = 'string'): array
    {
        return array_filter(['type' => $type, 'description' => $description, 'x-hidden' => true], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Moves a field into the collapsed "More options" section.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public static function advanced(array $field): array
    {
        return [...$field, 'x-advanced' => true];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private static function make(array $base, string $title, ?string $description = null, ?string $placeholder = null): array
    {
        $field = ['type' => $base['type'], 'title' => $title];

        if ($description !== null) {
            $field['description'] = $description;
        }

        if ($placeholder !== null) {
            $field['x-placeholder'] = $placeholder;
        }

        return [...$field, ...$base];
    }
}
