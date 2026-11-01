<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioMakeCallNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_make_call';
    }

    public function name(): string
    {
        return 'Twilio: Make Call';
    }

    public function description(): string
    {
        return 'Phones a number and speaks a message, plays a recording, or runs TwiML or a TwiML URL.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'from'],
            'properties' => [
                ...$this->credentialFields(),
                'from' => Field::dynamic('From number', 'twilio.numbers', 'A Twilio number of the account.', '+14155550123'),
                'to' => Field::text('To', 'The number to call, in international format.', '+14155550199'),
                'mode' => Field::select('When answered', ['say' => 'Say a message', 'play' => 'Play an audio file', 'twiml' => 'Run TwiML', 'url' => 'Fetch TwiML from a URL'], null, 'say'),
                'say' => Field::textarea('Message to say', 'For "Say a message".'),
                'voice' => Field::advanced(Field::select('Voice', ['Polly.Joanna' => 'Joanna (female)', 'Polly.Matthew' => 'Matthew (male)', 'alice' => 'Alice', 'man' => 'Man', 'woman' => 'Woman'], null, 'Polly.Joanna')),
                'language' => Field::advanced(Field::text('Language', 'For "Say a message".', 'en-US')),
                'play_url' => Field::text('Audio link', 'For "Play an audio file": an MP3 or WAV link.', 'https://example.com/message.mp3'),
                'twiml' => Field::textarea('TwiML', 'For "Run TwiML": starts with <Response>.'),
                'twiml_url' => Field::text('TwiML URL', 'For "Fetch TwiML from a URL".', 'https://example.com/voice.xml'),
                'record' => Field::advanced(Field::boolean('Record the call')),
                'timeout' => Field::advanced(Field::integer('Ring for (seconds)', 'How long to ring before giving up.', 30, 5, 600)),
                'machine_detection' => Field::advanced(Field::boolean('Detect answering machines', 'Tells a person from a machine (see answered_by on the call).')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $to = $this->phone($config['to'] ?? null, 'To');
        $from = $this->phone($config['from'] ?? null, 'From');
        $mode = (string) ($config['mode'] ?? 'say');
        $params = ['To' => $to, 'From' => $from, 'Timeout' => is_numeric($config['timeout'] ?? null) ? (int) $config['timeout'] : null, 'Record' => filter_var($config['record'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null, 'MachineDetection' => filter_var($config['machine_detection'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Enable' : null];

        $params += match ($mode) {
            'say' => ['Twiml' => $this->say($config)],
            'play' => ['Twiml' => '<Response><Play>'.htmlspecialchars($this->link($config, 'play_url', 'The audio link'), ENT_XML1 | ENT_QUOTES).'</Play></Response>'],
            'twiml' => ['Twiml' => $this->twiml($config)],
            'url' => ['Url' => $this->link($config, 'twiml_url', 'The TwiML URL')],
            default => throw new InvalidArgumentException("\"{$mode}\" is not a way to answer. Use say, play, twiml or url."),
        };

        return $this->shapeCall((array) $this->twilio($run, $config, 'POST', '/Calls.json', $params));
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function say(array $config): string
    {
        $text = $this->requiredString($config, 'say', 'The message to say');

        if (mb_strlen($text) > 4000) {
            throw new InvalidArgumentException('The message to say is over 4000 characters.');
        }

        $voice = htmlspecialchars((string) ($config['voice'] ?? 'Polly.Joanna'), ENT_XML1 | ENT_QUOTES);
        $language = htmlspecialchars((string) ($config['language'] ?? 'en-US'), ENT_XML1 | ENT_QUOTES);

        return '<Response><Say voice="'.$voice.'" language="'.$language.'">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES).'</Say></Response>';
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function twiml(array $config): string
    {
        $twiml = trim($this->requiredString($config, 'twiml', 'The TwiML'));

        return str_starts_with($twiml, '<Response') ? $twiml : throw new InvalidArgumentException('TwiML must start with <Response>.');
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    private function link(array $config, string $key, string $label): string
    {
        $url = $this->requiredString($config, $key, $label);

        return preg_match('#^https?://#i', $url) === 1 ? $url : throw new InvalidArgumentException("{$label} must start with http:// or https://.");
    }
}
