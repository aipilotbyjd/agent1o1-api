<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;
use RuntimeException;

class TwilioCheckVerificationNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_check_verification';
    }

    public function name(): string
    {
        return 'Twilio: Check Verification Code';
    }

    public function description(): string
    {
        return 'Checks the code a person typed. `valid` is true only when it is right (and not expired).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['service_sid', 'to', 'code'],
            'properties' => [
                ...$this->credentialFields(),
                'service_sid' => Field::dynamic('Verify service', 'twilio.verify_services', 'The Verify service to use. Create one in the Twilio Console under Verify > Services.', 'VA...'),
                'to' => Field::text('To', 'The same number or address the code was sent to.', '+14155550199'),
                'code' => Field::text('Code', 'What the person typed (4 to 10 digits).', '123456'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $service = $this->sid($config, 'service_sid', 'Verify service SID', 'VA');
        $code = trim($this->requiredString($config, 'code', 'The code'));
        $to = str_contains((string) ($config['to'] ?? ''), '@') ? $this->verifyTarget($config, 'email') : $this->phone($config['to'] ?? null, 'To');

        if (preg_match('/^\d{4,10}$/', $code) !== 1) {
            throw new InvalidArgumentException('A verification code is 4 to 10 digits.');
        }

        try {
            $check = (array) $this->twilio($run, $config, 'POST', "/v2/Services/{$service}/VerificationCheck", ['To' => $to, 'Code' => $code], 'verify');
        } catch (RuntimeException $exception) {
            // Twilio has no verification to check once it expired, was approved, or never was started.
            if (str_contains($exception->getMessage(), '20404')) {
                return ['valid' => false, 'status' => 'not_found', 'to' => $to, 'service_sid' => $service];
            }

            throw $exception;
        }

        return ['valid' => ($check['status'] ?? null) === 'approved', 'status' => $check['status'] ?? null, 'to' => $check['to'] ?? $to, 'service_sid' => $service];
    }
}
