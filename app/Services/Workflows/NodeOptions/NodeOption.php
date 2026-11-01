<?php

namespace App\Services\Workflows\NodeOptions;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One choice in a node field's dropdown. `value` is what gets saved into the
 * node's config; `label`/`description` are only shown.
 *
 * @implements Arrayable<string, string|int>
 */
final readonly class NodeOption implements Arrayable
{
    public string $label;

    public function __construct(
        public string|int $value,
        ?string $label = null,
        public ?string $description = null,
    ) {
        $this->label = $label !== null && trim($label) !== '' ? $label : (string) $value;
    }

    /**
     * @return array{value: string|int, label: string, description?: string}
     */
    public function toArray(): array
    {
        $option = ['value' => $this->value, 'label' => $this->label];

        if ($this->description !== null && $this->description !== '') {
            $option['description'] = $this->description;
        }

        return $option;
    }
}
