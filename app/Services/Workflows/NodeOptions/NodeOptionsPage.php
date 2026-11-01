<?php

namespace App\Services\Workflows\NodeOptions;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One page of a node field's dropdown. `nextCursor` is opaque to the client
 * and handed back as-is to load the next page; null means this is the last.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class NodeOptionsPage implements Arrayable
{
    /** @var list<NodeOption> */
    public array $options;

    public ?string $nextCursor;

    /**
     * @param  iterable<NodeOption>  $options
     */
    public function __construct(iterable $options, string|int|null $nextCursor = null)
    {
        $this->options = array_values([...$options]);
        $this->nextCursor = $nextCursor !== null && (string) $nextCursor !== '' ? (string) $nextCursor : null;
    }

    /**
     * @return array{options: list<array{value: string|int, label: string, description?: string}>, next_cursor: string|null}
     */
    public function toArray(): array
    {
        return [
            'options' => array_map(fn (NodeOption $option): array => $option->toArray(), $this->options),
            'next_cursor' => $this->nextCursor,
        ];
    }
}
