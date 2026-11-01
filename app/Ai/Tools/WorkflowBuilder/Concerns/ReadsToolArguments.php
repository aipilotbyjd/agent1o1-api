<?php

namespace App\Ai\Tools\WorkflowBuilder\Concerns;

use Closure;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Reads the builder tools' arguments defensively. They come from a model,
 * so any of them may be missing or the wrong shape: a `config_json` sent as
 * an object instead of a string, a number where a key belongs. laravel/ai
 * does not catch what a tool throws, so a single malformed call would
 * otherwise fail the whole turn instead of letting the model correct itself.
 */
trait ReadsToolArguments
{
    /**
     * Run a tool's work, handing any problem back to the model as the tool's
     * result: a bad argument as its own message, anything unexpected as a
     * generic one (and reported), never as an exception.
     *
     * @param  Closure(): string  $answer
     */
    protected function answer(Closure $answer): string
    {
        try {
            return $answer();
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);

            return "{$this->name()} failed unexpectedly, so nothing was changed. Check the arguments and try again.";
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function stringArgument(Request $request, string $name): string
    {
        $value = $this->optionalStringArgument($request, $name);

        if ($value === null) {
            throw new InvalidArgumentException("The {$name} argument is required.");
        }

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function optionalStringArgument(Request $request, string $name): ?string
    {
        $value = $request->all()[$name] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("The {$name} argument must be a string.");
        }

        return (string) $value;
    }

    /**
     * A JSON-object argument. The schemas ask for a JSON string, but models
     * also send the object itself, so both are accepted; anything that isn't
     * an object (a list, a number, a bare string) is refused.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function objectArgument(Request $request, string $name): array
    {
        $value = $request->all()[$name] ?? null;

        if (is_string($value)) {
            if (trim($value) === '') {
                return [];
            }

            try {
                $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException("{$name} must be a valid JSON object string.");
            }
        }

        if ($value === null || $value === []) {
            return [];
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException("{$name} must be a JSON object, like {\"field\": \"value\"}.");
        }

        return $value;
    }

    /**
     * A list-of-strings argument, accepted as a real list, a JSON-encoded
     * list, or a single string.
     *
     * @return array<int, string>
     *
     * @throws InvalidArgumentException
     */
    protected function stringListArgument(Request $request, string $name): array
    {
        $value = $request->all()[$name] ?? null;

        if (is_string($value) && str_starts_with(trim($value), '[')) {
            try {
                $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException("{$name} must be a list of strings.");
            }
        }

        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        foreach ($values as $item) {
            if (! is_string($item) || $item === '') {
                throw new InvalidArgumentException("{$name} must be a list of strings.");
            }
        }

        return array_values(array_unique($values));
    }
}
