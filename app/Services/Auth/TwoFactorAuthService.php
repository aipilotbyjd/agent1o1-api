<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TwoFactorAuthService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function enable(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret,
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function confirm(User $user, string $code): array
    {
        if (! $user->two_factor_secret) {
            throw new HttpException(422, 'Two-factor authentication has not been enabled yet.');
        }

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            throw new HttpException(422, 'The provided two-factor code is invalid.');
        }

        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $hashedCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $plainCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        if ($this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * @return array<int, string>
     */
    public function recoveryCodes(User $user): array
    {
        return $user->two_factor_recovery_codes ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $hashedCodes])->save();

        return $plainCodes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($recoveryCodes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

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

        $hashedCodes = array_map(fn (string $code): string => Hash::make($code), $plainCodes);

        return [$plainCodes, $hashedCodes];
    }
}
