<?php

namespace App\Services\Assistant\Channels;

use App\Models\Assistant\AssistantPhoneNumber;
use App\Models\Assistant\AssistantPhoneVerification;
use App\Models\User;
use App\Services\Assistant\Branding\BrandRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Proving a mobile number belongs to the member: a 6-digit code by text,
 * valid for a few minutes, with limits on resends and guesses. One account
 * per number.
 */
class PhoneVerification
{
    public const string E164 = '/^\+[1-9]\d{6,14}$/';

    public function __construct(
        private readonly TwilioSms $sms,
        private readonly BrandRepository $brands,
    ) {}

    public function start(User $user, string $phone): void
    {
        $config = config('assistant.channels.sms');
        $phone = preg_replace('/[\s().-]/', '', $phone) ?? $phone;

        if (preg_match(self::E164, $phone) !== 1) {
            throw ValidationException::withMessages(['phone' => 'Enter the number in international format, e.g. +14155550123.']);
        }

        if (AssistantPhoneNumber::query()->where('phone', $phone)->whereNot('user_id', $user->id)->whereNotNull('verified_at')->exists()) {
            throw ValidationException::withMessages(['phone' => 'That number is already linked to another account.']);
        }

        $recent = AssistantPhoneVerification::query()->where('user_id', $user->id)->where('created_at', '>', now()->subHour());

        if ((clone $recent)->where('created_at', '>', now()->subSeconds($config['resend_seconds']))->exists()) {
            throw ValidationException::withMessages(['phone' => 'Wait a minute before asking for another code.']);
        }

        if ($recent->count() >= $config['codes_per_hour']) {
            throw ValidationException::withMessages(['phone' => 'Too many codes this hour. Try again later.']);
        }

        $code = (string) random_int(100000, 999999);

        AssistantPhoneVerification::query()->create([
            'user_id' => $user->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($config['code_minutes']),
        ]);

        $this->sms->send($phone, "Your {$this->brands->current()->name} code is {$code}. It expires in {$config['code_minutes']} minutes.");
    }

    public function confirm(User $user, string $code): AssistantPhoneNumber
    {
        $verification = AssistantPhoneVerification::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($verification === null || $verification->attempts >= config('assistant.channels.sms.attempts_per_code')) {
            throw ValidationException::withMessages(['code' => 'That code has expired. Ask for a new one.']);
        }

        $verification->increment('attempts');

        if (! Hash::check(trim($code), $verification->code_hash)) {
            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        AssistantPhoneVerification::query()->where('user_id', $user->id)->delete();

        return AssistantPhoneNumber::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['phone' => $verification->phone, 'verified_at' => now()],
        );
    }
}
