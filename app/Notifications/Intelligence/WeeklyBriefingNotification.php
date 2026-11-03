<?php

namespace App\Notifications\Intelligence;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The weekly business briefing (`WeeklyBriefing`), mailed to the operators.
 */
class WeeklyBriefingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $briefing
     */
    public function __construct(public readonly array $briefing) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->briefing;
        $e = $b['economics'];
        $change = fn (?float $value): string => $value === null ? 'n/a' : sprintf('%+.1f%%', $value);

        $message = (new MailMessage)
            ->subject(sprintf('Weekly briefing %s to %s: $%s margin', $b['from'], $b['to'], number_format($e['gross_margin_usd'], 2)))
            ->line(sprintf('Revenue $%s (%s), provider cost $%s (%s), gross margin $%s (%s%%).',
                number_format($e['revenue_usd'], 2), $change($e['change']['revenue_usd']),
                number_format($e['provider_cost_usd'], 2), $change($e['change']['provider_cost_usd']),
                number_format($e['gross_margin_usd'], 2), $e['gross_margin_percent'] ?? 'n/a'))
            ->line(sprintf('Free and trial cost $%s. %d paying workspaces, %d active. %d AI calls, %.1f%% failed.',
                number_format($e['free_and_trial_cost_usd'], 2), $e['paying_workspaces'], $e['active_workspaces'], $e['ai_calls'], $e['failure_rate'] * 100))
            ->line(sprintf('Forecast provider spend next 7 days: $%s.', number_format($b['next_week_spend_usd'], 2)));

        foreach ([
            'Customers losing money' => array_map(fn (array $row): string => sprintf('%s: $%s margin', $row['name'] ?? $row['workspace_id'], number_format($row['gross_margin_usd'], 2)), $b['loss_customers']),
            'Margin at risk this period' => array_map(fn (array $row): string => sprintf('%s: projected loss $%s', $row['name'], number_format($row['projected_loss_usd'], 2)), $b['margin_at_risk']),
            'Churn risk' => array_map(fn (array $row): string => sprintf('%s (%d): %s', $row['name'], $row['score'], implode('; ', $row['factors'])), $b['churn_risk']),
            'Likely upgrades' => array_map(fn (array $row): string => sprintf('%s (%d): %s', $row['name'], $row['score'], implode('; ', $row['factors'])), $b['upgrade_candidates']),
            'Open recommendations' => array_map(fn (array $row): string => strtoupper($row['severity']).': '.$row['title'], $b['recommendations']),
        ] as $heading => $lines) {
            if ($lines === []) {
                continue;
            }

            $message->line('**'.$heading.'**');

            foreach ($lines as $line) {
                $message->line('- '.$line);
            }
        }

        return $message;
    }
}
