<?php

namespace App\Engine\Nodes\Apps\Mailchimp;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class MailchimpNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'add_subscriber' => $this->addSubscriber($input),
            'update_subscriber' => $this->updateSubscriber($input),
            'get_subscriber' => $this->getSubscriber($input),
            'remove_subscriber' => $this->removeSubscriber($input),
            'list_subscribers' => $this->listSubscribers($input),
            'list_campaigns' => $this->listCampaigns($input),
            'list_lists' => $this->listLists($input),
            'add_tag' => $this->addTag($input),
            default => $this->fail("Mailchimp: unknown operation '{$operation}'"),
        };
    }

    private function mcHttp(NodeInput $input): PendingRequest
    {
        $dc = $input->credentials['server_prefix'] ?? 'us1';

        return $this->http()
            ->baseUrl("https://{$dc}.api.mailchimp.com/3.0")
            ->withBasicAuth('anystring', $input->credentials['api_key'] ?? '');
    }

    private function addSubscriber(NodeInput $input): NodeResult
    {
        $response = $this->mcHttp($input)
            ->post("/lists/{$input->config['list_id']}/members", [
                'email_address' => $input->config['email'],
                'status' => $input->config['status'] ?? 'subscribed',
                'merge_fields' => $input->config['merge_fields'] ?? new \stdClass,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mailchimp add_subscriber failed: {$response->body()}");
    }

    private function updateSubscriber(NodeInput $input): NodeResult
    {
        $hash = md5(strtolower($input->config['email']));
        $response = $this->mcHttp($input)
            ->patch("/lists/{$input->config['list_id']}/members/{$hash}", array_filter([
                'status' => $input->config['status'] ?? null,
                'merge_fields' => $input->config['merge_fields'] ?? null,
            ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mailchimp update_subscriber failed: {$response->body()}");
    }

    private function getSubscriber(NodeInput $input): NodeResult
    {
        $hash = md5(strtolower($input->config['email']));
        $response = $this->mcHttp($input)
            ->get("/lists/{$input->config['list_id']}/members/{$hash}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mailchimp get_subscriber failed: {$response->body()}");
    }

    private function removeSubscriber(NodeInput $input): NodeResult
    {
        $hash = md5(strtolower($input->config['email']));
        $response = $this->mcHttp($input)
            ->patch("/lists/{$input->config['list_id']}/members/{$hash}", [
                'status' => 'unsubscribed',
            ]);

        return $response->successful()
            ? $this->success(['removed' => true])
            : $this->fail("Mailchimp remove_subscriber failed: {$response->body()}");
    }

    private function listSubscribers(NodeInput $input): NodeResult
    {
        $response = $this->mcHttp($input)
            ->get("/lists/{$input->config['list_id']}/members", [
                'count' => $input->config['count'] ?? 10,
                'status' => $input->config['status'] ?? null,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mailchimp list_subscribers failed: {$response->body()}");
    }

    private function listCampaigns(NodeInput $input): NodeResult
    {
        $response = $this->mcHttp($input)->get('/campaigns', array_filter([
            'count' => $input->config['count'] ?? 10,
            'status' => $input->config['status'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mailchimp list_campaigns failed: {$response->body()}");
    }

    private function listLists(NodeInput $input): NodeResult
    {
        $response = $this->mcHttp($input)->get('/lists', [
            'count' => $input->config['count'] ?? 10,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Mailchimp list_lists failed: {$response->body()}");
    }

    private function addTag(NodeInput $input): NodeResult
    {
        $hash = md5(strtolower($input->config['email']));
        $response = $this->mcHttp($input)
            ->post("/lists/{$input->config['list_id']}/members/{$hash}/tags", [
                'tags' => [['name' => $input->config['tag'], 'status' => 'active']],
            ]);

        return $response->successful()
            ? $this->success(['tagged' => true])
            : $this->fail("Mailchimp add_tag failed: {$response->body()}");
    }
}
