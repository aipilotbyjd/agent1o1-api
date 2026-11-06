<?php

namespace App\Services\Secrets;

use App\Models\Ai\AiEndpoint;
use Illuminate\Support\Arr;
use Throwable;

/**
 * The platform's own credentials (its AI provider keys, cloud credentials,
 * operator endpoint keys, OAuth client secrets, `APP_KEY`, service tokens)
 * and the one place that scrubs them out of text a workspace can read.
 *
 * With bring-your-own-key a workspace's calls run on its key and, behind
 * it, on the platform's. Whatever a failed call says (a provider error that
 * quotes the key it was sent, an HTTP error that quotes the URL with a
 * `?key=` in it) must not reach run errors, tool results the model reads,
 * or anything else a member can see. Every such message goes through
 * `redact()` before it is stored or shown.
 *
 * Two layers: the exact values the platform holds (as written and
 * URL-encoded, via `SecretRedactor`), and key-shaped strings (`sk-…`,
 * `AIza…`, `gsk_…`, a bearer token, a `?key=` parameter), which also catch
 * a key a provider echoes back masked or partly cut.
 */
class PlatformSecrets
{
    /**
     * Config trees that hold the platform's credentials.
     *
     * @var array<int, string>
     */
    private const array CONFIG_ROOTS = ['ai.providers', 'byok', 'services', 'broadcasting.connections', 'filesystems.disks', 'mail.mailers', 'database.connections', 'database.redis', 'cashier', 'reverb'];

    /**
     * A config entry whose last segment matches is a credential.
     */
    private const string SECRET_KEY_PATTERN = '/(?:^|[_.-])(?:key|secret|token|password|access_key_id|secret_access_key|session_token|client_secret|webhook_secret|signing_secret)$/i';

    /**
     * Shorter values are left alone: they are defaults like `none` or a
     * port, and replacing them would mangle ordinary text.
     */
    private const int MIN_LENGTH = 8;

    /**
     * Key-shaped strings, whoever's key they are.
     *
     * @var array<int, string>
     */
    private const array PATTERNS = [
        '/\bsk-[A-Za-z0-9_\-*.]{12,}/',
        '/\b(?:sk|pk|rk)_(?:live_|test_)?[A-Za-z0-9_*]{8,}/',
        '/\bAIza[0-9A-Za-z_\-]{20,}/',
        '/\bgsk_[A-Za-z0-9*]{16,}/',
        '/\bxai-[A-Za-z0-9*]{16,}/',
        '/\bhf_[A-Za-z0-9*]{16,}/',
        '/\bfw_[A-Za-z0-9*]{16,}/',
        '/\bAKIA[0-9A-Z]{16}\b/',
        '/\bya29\.[A-Za-z0-9_\-.]{16,}/',
        '/\bBearer\s+[A-Za-z0-9_\-.~+\/=]{16,}/i',
    ];

    /**
     * A credential in a URL's query string: the name stays, the value goes.
     */
    private const string QUERY_PATTERN = '/([?&](?:key|api_key|apikey|api-key|access_token|token|secret|client_secret)=)[^&\s"\'<>]+/i';

    public function __construct(private readonly SecretRedactor $redactor) {}

    /**
     * Every credential value the platform holds, longest first.
     *
     * @return array<int, string>
     */
    public function values(): array
    {
        $values = [(string) config('app.key'), ...array_map('strval', (array) config('app.previous_keys', []))];

        foreach (self::CONFIG_ROOTS as $root) {
            foreach (Arr::dot((array) config($root, [])) as $path => $value) {
                if (is_string($value) && preg_match(self::SECRET_KEY_PATTERN, (string) $path) === 1) {
                    $values[] = $value;
                }
            }
        }

        $values = [...$values, ...$this->endpointKeys()];

        return array_values(array_unique(array_filter(
            $values,
            fn (string $value): bool => mb_strlen(trim($value)) >= self::MIN_LENGTH,
        )));
    }

    /**
     * The text with the platform's credentials and anything key-shaped
     * replaced by `SecretRedactor::PLACEHOLDER`.
     *
     * @param  array<int, string>  $extraValues  other plaintext to scrub too, such as the workspace secrets a step used
     */
    public function redact(?string $text, array $extraValues = []): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        $text = $this->redactor->redactString($text, [...$extraValues, ...$this->values()]);
        $text = preg_replace(self::QUERY_PATTERN, '$1'.SecretRedactor::PLACEHOLDER, $text) ?? $text;

        return preg_replace(self::PATTERNS, SecretRedactor::PLACEHOLDER, $text) ?? $text;
    }

    /**
     * The keys of the operator's own endpoints, which live in the database
     * rather than in config.
     *
     * @return array<int, string>
     */
    private function endpointKeys(): array
    {
        try {
            return AiEndpoint::query()->get()
                ->map(fn (AiEndpoint $endpoint): string => (string) $endpoint->api_key)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
