<?php

it('keeps GlitchTip reporting off when no DSN is configured', function (): void {
    expect(config('sentry.dsn'))->toBeEmpty();
});

it('never sends personal data to GlitchTip by default', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.profiles_sample_rate'))->toBeNull();
});

it('does not report routine client errors', function (): void {
    expect(config('sentry.ignore_exceptions'))->toContain(
        Illuminate\Validation\ValidationException::class,
        Illuminate\Auth\AuthenticationException::class,
    );
});
