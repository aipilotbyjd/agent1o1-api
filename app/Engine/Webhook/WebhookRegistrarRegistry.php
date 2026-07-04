<?php

namespace App\Engine\Webhook;

use App\Contracts\WebhookRegistrar;

/**
 * The single map of app-specific provider → registrar class.
 *
 * All registrars implement WebhookRegistrar:
 *   - provider()                 — identifies the provider string
 *   - supportsAutoRegistration() — whether API registration is possible
 *   - checkExists()              — verify the webhook still exists
 *   - verifySignature()          — authenticate incoming payloads
 *
 * Auto-registerable providers support register() and unregister(). Discord
 * requires manual portal setup — supportsAutoRegistration() returns false.
 *
 * Use resolve() when you only need to verify a signature or check existence.
 * Use resolveRegisterable() when you need to register or unregister.
 *
 * To add a new provider: create the registrar in app/Engine/Webhook/ and add
 * an entry to REGISTRARS below.
 */
class WebhookRegistrarRegistry
{
    /** @var array<string, class-string<WebhookRegistrar>> */
    private const REGISTRARS = [
        'github' => GitHubRegistrar::class,
        'stripe' => StripeRegistrar::class,
        'slack' => SlackRegistrar::class,
        'discord' => DiscordRegistrar::class,
        'airtable' => AirtableRegistrar::class,
    ];

    /**
     * Resolve a registrar instance by provider name. Suitable for signature
     * verification and health checks. Null if the provider has no registrar.
     */
    public static function resolve(string $provider): ?WebhookRegistrar
    {
        $class = self::REGISTRARS[$provider] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * Resolve a registrar that supports programmatic registration. Null if the
     * provider has no registrar or only supports manual setup (e.g. Discord).
     */
    public static function resolveRegisterable(string $provider): ?WebhookRegistrar
    {
        $registrar = self::resolve($provider);

        return ($registrar !== null && $registrar->supportsAutoRegistration()) ? $registrar : null;
    }

    public static function supports(string $provider): bool
    {
        return isset(self::REGISTRARS[$provider]);
    }

    public static function supportsAutoRegistration(string $provider): bool
    {
        $registrar = self::resolve($provider);

        return $registrar !== null && $registrar->supportsAutoRegistration();
    }

    /**
     * @return list<string>
     */
    public static function providers(): array
    {
        return array_keys(self::REGISTRARS);
    }
}
