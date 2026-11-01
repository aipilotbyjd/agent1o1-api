<?php

namespace App\Services\SuperAdmin\Auth;

use App\Models\SuperAdmin\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Time-based one-time passwords for super admins. Mandatory: an account
 * without confirmed two-factor authentication only ever receives a "setup"
 * token (see `SuperAdminTokenService`).
 */
class SuperAdminTwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function enable(SuperAdmin $admin): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl(config('app.name').' Super Admin', $admin->email, $secret),
        ];
    }

    /**
     * @return array<int, string> The plain recovery codes, shown once.
     */
    public function confirm(SuperAdmin $admin, string $code): array
    {
        if (! $admin->two_factor_secret) {
            throw new HttpException(422, 'Two-factor authentication has not been enabled yet.');
        }

        if (! $this->google2fa->verifyKey($admin->two_factor_secret, $code)) {
            throw new HttpException(422, 'The provided two-factor code is invalid.');
        }

        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $admin->forceFill([
            'two_factor_recovery_codes' => $hashedCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $plainCodes;
    }

    /**
     * Clears the secret and recovery codes. Used when an owner or the command
     * line resets a locked-out admin; the caller revokes their sessions.
     */
    public function reset(SuperAdmin $admin): void
    {
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Accepts an authenticator code or a single-use recovery code: for
     * signing in.
     */
    public function verifyCode(SuperAdmin $admin, string $code): bool
    {
        return $this->verifyTotp($admin, $code) || $this->consumeRecoveryCode($admin, $code);
    }

    /**
     * Authenticator code only: for step-up confirmation of a sensitive action,
     * where a recovery code should not be spent.
     */
    public function verifyTotp(SuperAdmin $admin, string $code): bool
    {
        if (! $admin->hasTwoFactorEnabled() || ! $admin->two_factor_secret) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($admin->two_factor_secret, $code);
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(SuperAdmin $admin): array
    {
        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $admin->forceFill(['two_factor_recovery_codes' => $hashedCodes])->save();

        return $plainCodes;
    }

    private function consumeRecoveryCode(SuperAdmin $admin, string $code): bool
    {
        $recoveryCodes = $admin->two_factor_recovery_codes ?? [];

        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($recoveryCodes[$index]);
                $admin->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>} [plainCodes, hashedCodes]
     */
    private function generateRecoveryCodes(): array
    {
        $plainCodes = collect(range(1, 8))
            ->map(fn (): string => Str::random(10).'-'.Str::random(10))
            ->all();

        return [$plainCodes, array_map(fn (string $code): string => Hash::make($code), $plainCodes)];
    }
}
