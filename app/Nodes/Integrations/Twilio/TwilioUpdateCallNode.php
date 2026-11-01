<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioUpdateCallNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_update_call';
    }

    public function name(): string
    {
        return 'Twilio: End or Redirect Call';
    }

    public function description(): string
    {
        return 'Hangs up a call, cancels one that hasn\'t been answered, or sends it to new TwiML while it is going.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['call_sid'],
            'properties' => [
                ...$this->credentialFields(),
                'call_sid' => Field::text('Call SID', 'It starts with CA.', 'CA...'),
                'action' => Field::select('Action', ['hangup' => 'Hang up', 'cancel' => 'Cancel (not yet answered)', 'redirect' => 'Redirect to TwiML'], null, 'hangup'),
                'twiml' => Field::textarea('TwiML', 'For "Redirect": starts with <Response>.'),
                'twiml_url' => Field::text('TwiML URL', 'For "Redirect": a URL that returns TwiML (instead of TwiML above).'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sid = $this->sid($config, 'call_sid', 'Call SID', 'CA');
        $action = (string) ($config['action'] ?? 'hangup');

        $params = match ($action) {
            'hangup' => ['Status' => 'completed'],
            'cancel' => ['Status' => 'canceled'],
            'redirect' => $this->redirect($config),
            default => throw new InvalidArgumentException("\"{$action}\" is not an action. Use hangup, cancel or redirect."),
        };

        return $this->shapeCall((array) $this->twilio($run, $config, 'POST', "/Calls/{$sid}.json", $params));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    private function redirect(array $config): array
    {
        $twiml = trim((string) ($config['twiml'] ?? ''));
        $url = trim((string) ($config['twiml_url'] ?? ''));

        if ($twiml !== '') {
            return str_starts_with($twiml, '<Response') ? ['Twiml' => $twiml] : throw new InvalidArgumentException('TwiML must start with <Response>.');
        }

        return preg_match('#^https?://#i', $url) === 1 ? ['Url' => $url] : throw new InvalidArgumentException('Redirecting needs TwiML or a TwiML URL.');
    }
}
