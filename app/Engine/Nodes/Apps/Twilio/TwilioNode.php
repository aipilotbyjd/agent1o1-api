<?php

namespace App\Engine\Nodes\Apps\Twilio;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class TwilioNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send_sms' => $this->sendSms($input),
            'send_whatsapp' => $this->sendWhatsapp($input),
            'make_call' => $this->makeCall($input),
            'send_verification' => $this->sendVerification($input),
            'check_verification' => $this->checkVerification($input),
            default => $this->fail("Twilio: unknown operation '{$operation}'"),
        };
    }

    private function twilioHttp(NodeInput $input): PendingRequest
    {
        $sid = $input->credentials['account_sid'] ?? '';

        return $this->http()
            ->baseUrl("https://api.twilio.com/2010-04-01/Accounts/{$sid}")
            ->withBasicAuth($sid, $input->credentials['auth_token'] ?? '')
            ->asForm();
    }

    private function sendSms(NodeInput $input): NodeResult
    {
        $response = $this->twilioHttp($input)->post('/Messages.json', [
            'To' => $input->config['to'],
            'From' => $input->config['from'] ?? ($input->credentials['from_number'] ?? ''),
            'Body' => $input->config['body'] ?? '',
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twilio send_sms failed: {$response->body()}");
    }

    private function sendWhatsapp(NodeInput $input): NodeResult
    {
        $response = $this->twilioHttp($input)->post('/Messages.json', [
            'To' => 'whatsapp:'.$input->config['to'],
            'From' => 'whatsapp:'.($input->config['from'] ?? ($input->credentials['from_number'] ?? '')),
            'Body' => $input->config['body'] ?? '',
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twilio send_whatsapp failed: {$response->body()}");
    }

    private function makeCall(NodeInput $input): NodeResult
    {
        $response = $this->twilioHttp($input)->post('/Calls.json', [
            'To' => $input->config['to'],
            'From' => $input->config['from'] ?? ($input->credentials['from_number'] ?? ''),
            'Url' => $input->config['twiml_url'] ?? 'http://demo.twilio.com/docs/voice.xml',
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twilio make_call failed: {$response->body()}");
    }

    private function sendVerification(NodeInput $input): NodeResult
    {
        $serviceSid = $input->config['service_sid'] ?? $input->credentials['verify_service_sid'] ?? '';
        $sid = $input->credentials['account_sid'] ?? '';

        $response = $this->http()
            ->baseUrl("https://verify.twilio.com/v2/Services/{$serviceSid}")
            ->withBasicAuth($sid, $input->credentials['auth_token'] ?? '')
            ->asForm()
            ->post('/Verifications', [
                'To' => $input->config['to'],
                'Channel' => $input->config['channel'] ?? 'sms',
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Twilio send_verification failed: {$response->body()}");
    }

    private function checkVerification(NodeInput $input): NodeResult
    {
        $serviceSid = $input->config['service_sid'] ?? $input->credentials['verify_service_sid'] ?? '';
        $sid = $input->credentials['account_sid'] ?? '';

        $response = $this->http()
            ->baseUrl("https://verify.twilio.com/v2/Services/{$serviceSid}")
            ->withBasicAuth($sid, $input->credentials['auth_token'] ?? '')
            ->asForm()
            ->post('/VerificationCheck', [
                'To' => $input->config['to'],
                'Code' => $input->config['code'],
            ]);

        $data = $response->json();

        return $response->successful()
            ? $this->success(['valid' => ($data['status'] ?? '') === 'approved', 'status' => $data['status'] ?? ''])
            : $this->fail("Twilio check_verification failed: {$response->body()}");
    }
}
