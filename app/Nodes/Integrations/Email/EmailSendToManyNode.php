<?php

namespace App\Nodes\Integrations\Email;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;
use Throwable;

class EmailSendToManyNode extends AbstractEmailNode
{
    /**
     * More than this in one step is a campaign, not a flow step.
     */
    private const int MAX_RECIPIENTS = 100;

    public function type(): string
    {
        return 'email_send_to_many';
    }

    public function name(): string
    {
        return 'Email: Send to Many';
    }

    public function description(): string
    {
        return 'Sends the same email separately to each address in a list, so recipients never see each other. Reports who it reached and who it could not.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['recipients', 'subject', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'recipients' => Field::list('Recipients', 'One address each, up to '.self::MAX_RECIPIENTS.'. A list from an earlier step works.', 'name@example.com'),
                ...$this->messageFields(),
                'stop_on_error' => Field::advanced(Field::boolean('Stop at the first failure', 'Fail the step at the first address the server refuses, instead of carrying on with the rest.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $recipients = $this->addresses($config['recipients'] ?? null, 'Recipients');

        if ($recipients === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException('At most '.self::MAX_RECIPIENTS.' recipients can be emailed in one step. Split the list.');
        }

        $account = $this->resolveCredentialData($run, $config);
        $stopOnError = filter_var($config['stop_on_error'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sent = [];
        $failed = [];

        foreach ($recipients as $recipient) {
            try {
                // Cc and Bcc would put every recipient on every copy: they are single-send settings.
                $message = $this->buildMessage([...$config, 'cc' => null, 'bcc' => null], $account, [$recipient]);
                $this->deliver($account, $message);
                $sent[] = $recipient;
            } catch (Throwable $e) {
                if ($stopOnError) {
                    throw $e;
                }

                $failed[] = ['email' => $recipient, 'error' => $e->getMessage()];
            }
        }

        return ['sent' => $sent, 'sent_count' => count($sent), 'failed' => $failed, 'failed_count' => count($failed)];
    }
}
