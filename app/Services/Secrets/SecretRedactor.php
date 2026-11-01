<?php

namespace App\Services\Secrets;

/**
 * Scrubs the plaintext of the secrets a step used out of anything that gets
 * persisted: a node's output and its error message. Without this a node that
 * echoes its own config back (an HTTP node returning the request it made, an
 * API returning the token it was called with, an exception quoting the URL)
 * would write the secret straight into `node_runs`, where every run-viewer in
 * the workspace can read it.
 *
 * Matches ignore case and also catch the URL-encoded forms, since that's how
 * a value usually comes back changed: a hostname lowercased in a DNS error,
 * a query parameter percent-encoded in the URL an exception quotes.
 *
 * Complements `StepFailureHandler`'s pattern-based redaction, which catches
 * key-shaped strings the engine never saw stored.
 */
final class SecretRedactor
{
    public const string PLACEHOLDER = '[redacted]';

    /**
     * @param  array<int, string>  $values
     */
    public function redact(mixed $subject, array $values): mixed
    {
        if ($values === []) {
            return $subject;
        }

        if (is_array($subject)) {
            return array_map(fn (mixed $item): mixed => $this->redact($item, $values), $subject);
        }

        if (! is_string($subject)) {
            return $subject;
        }

        return str_ireplace($this->forms($values), self::PLACEHOLDER, $subject);
    }

    /**
     * @param  array<int, string>  $values
     */
    public function redactString(string $subject, array $values): string
    {
        return $values === [] ? $subject : str_ireplace($this->forms($values), self::PLACEHOLDER, $subject);
    }

    /**
     * Each value as written and URL-encoded both ways, longest first so a
     * longer form is never left half-replaced by a shorter one inside it.
     *
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function forms(array $values): array
    {
        $forms = array_unique(array_merge(...array_map(
            fn (string $value): array => [$value, rawurlencode($value), urlencode($value)],
            $values,
        )));

        usort($forms, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $forms;
    }
}
