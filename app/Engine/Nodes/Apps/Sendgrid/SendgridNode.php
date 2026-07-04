<?php

namespace App\Engine\Nodes\Apps\Sendgrid;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class SendgridNode extends AppNode
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send_email' => $this->sendEmail($input),
            'send_template' => $this->sendTemplate($input),
            'add_contact', 'add_to_list' => $this->addContact($input),
            'list_contacts' => $this->listContacts($input),
            default => $this->fail("Sendgrid: unknown operation '{$operation}'"),
        };
    }

    private function sendEmail(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)->post('/mail/send', [
            'personalizations' => [[
                'to' => [['email' => $input->config['to']]],
                'subject' => $input->config['subject'] ?? '',
            ]],
            'from' => ['email' => $input->config['from']],
            'content' => [[
                'type' => ($input->config['is_html'] ?? false) ? 'text/html' : 'text/plain',
                'value' => $input->config['body'] ?? '',
            ]],
        ]);

        return $response->successful()
            ? $this->success(['sent' => true])
            : $this->fail("Sendgrid send_email failed: {$response->body()}");
    }

    private function sendTemplate(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)->post('/mail/send', [
            'personalizations' => [[
                'to' => [['email' => $input->config['to']]],
                'dynamic_template_data' => $input->config['template_data'] ?? [],
            ]],
            'from' => ['email' => $input->config['from']],
            'template_id' => $input->config['template_id'],
        ]);

        return $response->successful()
            ? $this->success(['sent' => true])
            : $this->fail("Sendgrid send_template failed: {$response->body()}");
    }

    private function listContacts(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/marketing/contacts/search', array_filter([
                'query' => $input->config['query'] ?? 'email IS NOT NULL',
                'page_size' => $input->config['limit'] ?? 50,
            ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Sendgrid list_contacts failed: {$response->body()}");
    }

    private function addContact(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)->put('/marketing/contacts', [
            'contacts' => [array_filter([
                'email' => $input->config['email'],
                'first_name' => $input->config['first_name'] ?? null,
                'last_name' => $input->config['last_name'] ?? null,
            ])],
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Sendgrid add_contact failed: {$response->body()}");
    }
}
