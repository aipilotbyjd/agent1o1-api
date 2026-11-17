<?php

namespace Database\Factories\Notifications;

use App\Enums\Notifications\NotificationType;
use App\Models\Notifications\AccountNotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountNotificationPreference>
 */
class AccountNotificationPreferenceFactory extends Factory
{
    protected $model = AccountNotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => NotificationType::AssistantDailyReport->value,
            'in_app' => true,
            'email' => true,
            'email_digest' => false,
        ];
    }
}
