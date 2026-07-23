<?php

namespace App\Notifications;

use App\Models\Run;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExecutionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Run $execution) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workflow = $this->execution->workflow;
        $errorMessage = $this->execution->error['message'] ?? 'Unknown error';
        $duration = $this->execution->duration_ms
            ? round($this->execution->duration_ms / 1000, 2).'s'
            : 'N/A';

        return (new MailMessage)
            ->error()
            ->subject("Workflow failed: {$workflow?->name}")
            ->line("The workflow **{$workflow?->name}** failed during execution.")
            ->line("**Error:** {$errorMessage}")
            ->line("**Duration:** {$duration}")
            ->line("**Execution ID:** {$this->execution->id}")
            ->action('View Execution', config('app.frontend_url')."/executions/{$this->execution->id}")
            ->line('Check the execution logs for more details.');
    }
}
