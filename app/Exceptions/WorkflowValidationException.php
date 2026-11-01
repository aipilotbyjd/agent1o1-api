<?php

namespace App\Exceptions;

use App\Services\Workflows\ValidationIssue;
use RuntimeException;

/**
 * Thrown by `Workflow::replaceGraph()` (config-schema issues only) and
 * `Workflow::publishVersion()` (full `GraphValidator` run): mapped to a 422
 * response in `bootstrap/app.php`. See docs/WORKFLOWS_PLAN.md's "Validation"
 * section for the checks that can produce these errors.
 *
 * The response carries both `errors` (the technical wording existing
 * clients read) and `issues` (each pointed at its node and config field,
 * with a message for the person building the workflow) which the editor
 * uses to list what to fix and jump to it.
 */
class WorkflowValidationException extends RuntimeException
{
    /**
     * @var array<int, ValidationIssue>
     */
    private readonly array $issues;

    /**
     * @param  array<int, string|ValidationIssue>  $problems
     */
    public function __construct(array $problems)
    {
        $this->issues = array_map(
            fn (string|ValidationIssue $problem): ValidationIssue => $problem instanceof ValidationIssue ? $problem : new ValidationIssue($problem),
            array_values($problems),
        );

        parent::__construct(self::summary(count($this->issues)));
    }

    /**
     * Shown to the person building the workflow, so it says how much needs
     * fixing rather than naming the graph; `issues()` says what and where.
     */
    private static function summary(int $count): string
    {
        return $count === 1
            ? 'One thing needs fixing before this workflow can run.'
            : "{$count} things need fixing before this workflow can run.";
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return array_map(fn (ValidationIssue $issue): string => $issue->text(), $this->issues);
    }

    /**
     * @return array<int, array{node_key: ?string, field: ?string, message: string}>
     */
    public function issues(): array
    {
        return array_map(fn (ValidationIssue $issue): array => $issue->toArray(), $this->issues);
    }
}
