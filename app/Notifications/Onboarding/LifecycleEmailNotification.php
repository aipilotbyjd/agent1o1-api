<?php

namespace App\Notifications\Onboarding;

use App\Enums\Onboarding\LifecycleEmail;
use App\Enums\Queue;
use App\Models\User;
use App\Services\Broadcasts\MarketingPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * One email of a new account's first weeks (see `LifecycleEmails`). Every
 * one is plain, short and ends in a single next step. They count as
 * marketing mail, so each carries the marketing unsubscribe link and the
 * one-click unsubscribe headers.
 */
class LifecycleEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{workspace_id: string|null, templates?: array<int, array<string, mixed>>, runs_this_week?: int}  $details
     */
    public function __construct(
        public readonly LifecycleEmail $email,
        public readonly array $details,
    ) {
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
        $mail = match ($this->email) {
            LifecycleEmail::Welcome => $this->welcome(),
            LifecycleEmail::FirstRunNudge => $this->firstRunNudge(),
            LifecycleEmail::AutomateIt => $this->automateIt(),
            LifecycleEmail::RoleTemplates => $this->roleTemplates(),
            LifecycleEmail::FirstWeekRecap => $this->firstWeekRecap(),
            LifecycleEmail::WinBack => $this->winBack(),
            LifecycleEmail::LastCheckIn => $this->lastCheckIn(),
        };

        $mail->greeting('Hi '.str($notifiable->name)->before(' ').',');

        return $notifiable instanceof User ? $this->withUnsubscribe($mail, $notifiable) : $mail;
    }

    private function welcome(): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->line('Thanks for signing up. The fastest way to see what '.config('app.name').' can do is to run one workflow on example data. It takes about five minutes, and nothing is sent anywhere until you say so.')
            ->action('Run your first workflow', $this->url('/onboarding'))
            ->line('One question: what is the first task you want to automate? Reply to this email and tell us. Every reply is read.');
    }

    private function firstRunNudge(): MailMessage
    {
        return (new MailMessage)
            ->subject('Your first workflow is one click away')
            ->line('You picked up where most people start, but your first run has not happened yet.')
            ->line('Press Run on the example data and you will see the result in under a minute. You can connect your own apps afterwards.')
            ->action('Finish my first run', $this->url('/onboarding'));
    }

    private function automateIt(): MailMessage
    {
        return (new MailMessage)
            ->subject('Make your workflow run by itself')
            ->line('Your workflow worked. Now let it do that without you: turn on its trigger and it runs every time something happens, like a new form entry or a new email.')
            ->action('Put it on autopilot', $this->workspaceUrl('playbooks'))
            ->line('You can switch it off again at any time.');
    }

    private function roleTemplates(): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('3 workflows teams like yours start with')
            ->line('Not sure what to build first? These are the starting points people in your role pick most often:');

        foreach ($this->details['templates'] ?? [] as $template) {
            $mail->line('**'.$template['name'].'**: '.$template['description']);
        }

        return $mail
            ->action('Browse templates', $this->workspaceUrl('blueprints'))
            ->line('Each one runs on example data first, so you can see what it does before connecting anything.');
    }

    private function firstWeekRecap(): MailMessage
    {
        $runs = (int) ($this->details['runs_this_week'] ?? 0);
        $times = $runs === 1 ? 'once' : "{$runs} times";

        return (new MailMessage)
            ->subject('Your first week: your workflows ran '.$times)
            ->line("In your first week your workflows ran {$times}. That is work you did not have to do by hand.")
            ->line('Teams get the most out of '.config('app.name').' once two or three workflows run on their own. Your dashboard shows what to set up next.')
            ->action('See what to automate next', $this->workspaceUrl('dashboard'));
    }

    private function winBack(): MailMessage
    {
        return (new MailMessage)
            ->subject('New ways to put your busywork on autopilot')
            ->line('It has been a while since your workflows last ran. We have added new templates since you signed up, and each one runs on example data first.')
            ->action('See what is new', $this->workspaceUrl('blueprints'))
            ->line('Stuck on something? Reply to this email and tell us what you were trying to do.');
    }

    private function lastCheckIn(): MailMessage
    {
        return (new MailMessage)
            ->subject('Can we help you get started?')
            ->line('You signed up a few weeks ago but have not had a workflow run lately. If something got in the way, we would like to know: reply with one line and we will help you set it up.')
            ->action('Open '.config('app.name'), $this->workspaceUrl('dashboard'))
            ->line('This is the last email of this kind we will send you.');
    }

    private function withUnsubscribe(MailMessage $mail, User $user): MailMessage
    {
        $preferences = app(MarketingPreferences::class);
        $oneClickUrl = $preferences->oneClickUrl($user);

        return $mail
            ->salutation("Not useful? [Stop these emails]({$preferences->pageUrl($user)}).")
            ->withSymfonyMessage(function (Email $message) use ($oneClickUrl): void {
                $message->getHeaders()
                    ->addTextHeader('List-Unsubscribe', "<{$oneClickUrl}>")
                    ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }

    /**
     * A page in the workspace the user works in, or the app's start page
     * when they have none.
     */
    private function workspaceUrl(string $page): string
    {
        $workspaceId = $this->details['workspace_id'] ?? null;

        return $this->url($workspaceId === null ? '/' : "/{$workspaceId}/{$page}");
    }

    private function url(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
