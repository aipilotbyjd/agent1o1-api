<?php

namespace App\Notifications\Onboarding;

use App\Enums\Queue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one nudge a user gets after leaving onboarding part way (see
 * `OnboardingReminders`). The link opens the wizard, which resumes at the
 * step they stopped on.
 */
class FinishOnboardingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $stepsLeft)
    {
        $this->onQueue(Queue::Notification->value);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $onboardingUrl = rtrim((string) config('app.frontend_url'), '/').'/onboarding';
        $steps = $this->stepsLeft === 1 ? '1 step' : "{$this->stepsLeft} steps";

        return (new MailMessage)
            ->subject("You're {$steps} from your first automation")
            ->greeting('Hi '.str($notifiable->name)->before(' ').',')
            ->line('You started setting up '.config('app.name')." and you're {$steps} from your first automation.")
            ->line('Pick up right where you left off: your progress is saved.')
            ->action('Finish setting up', $onboardingUrl)
            ->line('It takes about two minutes.');
    }
}
