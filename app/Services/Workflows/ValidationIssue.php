<?php

namespace App\Services\Workflows;

/**
 * One thing wrong with a workflow, pointed at where it is: the node (by
 * key) and the top-level config field it's in, when it's about one, with a
 * message written for the person building the workflow. `detail` is the
 * older technical wording (`Node 'a': config.prompt is required.`) that the
 * plain `errors` list still carries for existing clients.
 */
final readonly class ValidationIssue
{
    public function __construct(
        public string $message,
        public ?string $nodeKey = null,
        public ?string $field = null,
        public ?string $detail = null,
    ) {}

    /**
     * A config issue from `ConfigSchemaValidator::issues()`, placed on a node.
     *
     * @param  array{field: ?string, message: string, detail: string}  $issue
     */
    public static function forNodeConfig(string $nodeKey, array $issue): self
    {
        return new self($issue['message'], $nodeKey, $issue['field'], "Node '{$nodeKey}': {$issue['detail']}");
    }

    public function text(): string
    {
        return $this->detail ?? $this->message;
    }

    /**
     * @return array{node_key: ?string, field: ?string, message: string}
     */
    public function toArray(): array
    {
        return ['node_key' => $this->nodeKey, 'field' => $this->field, 'message' => $this->message];
    }
}
