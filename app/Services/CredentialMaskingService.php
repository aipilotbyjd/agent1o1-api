<?php

namespace App\Services;

class CredentialMaskingService
{
    /**
     * Substrings that mark a key as sensitive and worth redacting.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'authorization',
        'auth', 'access_key', 'private_key', 'client_secret', 'refresh_token',
        'credential', 'bearer', 'signature',
    ];

    private const REDACTED = '***redacted***';

    /**
     * Recursively redact sensitive values in an array for safe logging.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function mask(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->mask($value);

                continue;
            }

            $masked[$key] = $this->isSensitive((string) $key) ? self::REDACTED : $value;
        }

        return $masked;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
