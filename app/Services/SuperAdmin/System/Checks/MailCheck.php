<?php

namespace App\Services\SuperAdmin\System\Checks;

/**
 * Configuration only: sending a test email on every check would be noise.
 */
class MailCheck implements HealthCheck
{
    public function name(): string
    {
        return 'mail';
    }

    public function run(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return ['status' => self::DEGRADED, 'message' => "Mail is set to the [{$mailer}] mailer: nothing is delivered.", 'details' => ['mailer' => $mailer]];
        }

        if (blank(config('mail.from.address'))) {
            return ['status' => self::DEGRADED, 'message' => 'Mail has no from address.', 'details' => ['mailer' => $mailer]];
        }

        return ['status' => self::HEALTHY, 'message' => "Mail uses the [{$mailer}] mailer.", 'details' => ['mailer' => $mailer]];
    }
}
