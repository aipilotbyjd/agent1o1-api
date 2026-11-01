<?php

namespace App\Services\Assistant\Branding;

use Illuminate\Support\Str;

/**
 * The assistant's public identity at one moment: resolved by
 * `BrandRepository`, never built from config by consumers. Everything
 * user-facing that names the assistant (prompts, mail, Slack, SMS, the API
 * the frontend reads) goes through this object, so renaming the product is
 * a config change. See docs/ASSISTANT_BRANDING_PLAN.md.
 */
final readonly class Brand
{
    /**
     * @param  array<string, string>  $features  feature key => name template (may contain `:name`)
     * @param  list<string>  $emailAliases  former local parts that still route to the assistant
     */
    public function __construct(
        public string $name,
        public string $tagline,
        public string $description,
        public string $emoji,
        public string $color,
        public string $iconUrl,
        public string $avatarUrl,
        public array $features,
        public string $emailLocalPart,
        public array $emailAliases,
        public string $inboundDomain,
        public string $smsSignature,
    ) {}

    /**
     * A feature's display name, e.g. `daily` → ":name Daily" → "<brand> Daily". Unknown keys
     * fall back to the headline-cased key so a missing config entry never
     * leaks a raw identifier into the UI.
     */
    public function feature(string $key): string
    {
        return $this->withName($this->features[$key] ?? Str::headline($key));
    }

    /**
     * Replaces `:name` with the brand name, for prompt and message
     * templates kept brand-neutral.
     */
    public function withName(string $template): string
    {
        return str_replace(':name', $this->name, $template);
    }

    public function email(): string
    {
        return "{$this->emailLocalPart}@{$this->inboundDomain}";
    }

    /**
     * Whether mail sent to `$address` is meant for the assistant: the
     * current address or any former one, so a rename never strands people
     * still writing to the old name.
     */
    public function acceptsEmailTo(string $address): bool
    {
        $address = Str::lower(trim($address));

        if (! Str::endsWith($address, '@'.Str::lower($this->inboundDomain))) {
            return false;
        }

        $localPart = Str::before($address, '@');

        return collect([$this->emailLocalPart, ...$this->emailAliases])
            ->map(fn (string $part): string => Str::lower($part))
            ->contains($localPart);
    }

    public function smsSignature(): string
    {
        return $this->withName($this->smsSignature);
    }

    /**
     * The shape the frontend reads from `GET /app-config` and `GET /assistant`.
     *
     * @return array{name: string, tagline: string, description: string, emoji: string, color: string, icon_url: string, avatar_url: string, features: array<string, string>, email: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'color' => $this->color,
            'icon_url' => $this->absoluteUrl($this->iconUrl),
            'avatar_url' => $this->absoluteUrl($this->avatarUrl),
            'features' => collect($this->features)->keys()->mapWithKeys(fn (string $key): array => [$key => $this->feature($key)])->all(),
            'email' => $this->email(),
        ];
    }

    private function absoluteUrl(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }
}
