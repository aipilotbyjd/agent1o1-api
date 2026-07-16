<?php

namespace App\Engine\Nodes\Apps\Google;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;

class GmailNode extends AppNode
{
    public const TYPE = 'gmail';

    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send_email' => $this->sendEmail($input),
            'reply_to_message' => $this->replyToMessage($input),
            'get_message' => $this->getMessage($input),
            'list_messages' => $this->listMessages($input),
            'modify_message' => $this->modifyMessage($input),
            'add_label' => $this->addLabel($input),
            'list_labels' => $this->listLabels($input),
            'delete_message' => $this->deleteMessage($input),
            'create_draft' => $this->createDraft($input),
            default => $this->fail("Gmail: unknown operation '{$operation}'"),
        };
    }

    private function sendEmail(NodeInput $input): NodeResult
    {
        $to = $input->config['to'];
        $subject = $input->config['subject'] ?? '';
        $body = $input->config['body'] ?? '';
        $isHtml = $input->config['is_html'] ?? false;

        $contentType = $isHtml ? 'text/html' : 'text/plain';
        $raw = base64_encode(
            "To: {$to}\r\n".
            "Subject: {$subject}\r\n".
            "Content-Type: {$contentType}; charset=utf-8\r\n\r\n".
            $body
        );

        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/users/me/messages/send', ['raw' => strtr($raw, '+/', '-_')]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail send_email failed: {$response->body()}");
    }

    private function replyToMessage(NodeInput $input): NodeResult
    {
        $messageId = $input->config['message_id'];
        $threadId = $input->config['thread_id'];
        $to = $input->config['to'];
        $subject = $input->config['subject'] ?? '';
        $body = $input->config['body'] ?? '';

        $raw = base64_encode(
            "To: {$to}\r\n".
            "Subject: Re: {$subject}\r\n".
            "In-Reply-To: {$messageId}\r\n".
            "References: {$messageId}\r\n".
            "Content-Type: text/plain; charset=utf-8\r\n\r\n".
            $body
        );

        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/users/me/messages/send', [
                'raw' => strtr($raw, '+/', '-_'),
                'threadId' => $threadId,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail reply_to_message failed: {$response->body()}");
    }

    private function getMessage(NodeInput $input): NodeResult
    {
        $messageId = $input->config['message_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get("/users/me/messages/{$messageId}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail get_message failed: {$response->body()}");
    }

    private function listMessages(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/users/me/messages', [
                'q' => $input->config['query'] ?? '',
                'maxResults' => $input->config['max_results'] ?? 10,
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail list_messages failed: {$response->body()}");
    }

    private function modifyMessage(NodeInput $input): NodeResult
    {
        $messageId = $input->config['message_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/users/me/messages/{$messageId}/modify", [
                'addLabelIds' => $input->config['add_label_ids'] ?? [],
                'removeLabelIds' => $input->config['remove_label_ids'] ?? [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail modify_message failed: {$response->body()}");
    }

    private function addLabel(NodeInput $input): NodeResult
    {
        $messageId = $input->config['message_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/users/me/messages/{$messageId}/modify", [
                'addLabelIds' => (array) $input->config['label_ids'],
                'removeLabelIds' => [],
            ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail add_label failed: {$response->body()}");
    }

    private function listLabels(NodeInput $input): NodeResult
    {
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->get('/users/me/labels');

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail list_labels failed: {$response->body()}");
    }

    private function deleteMessage(NodeInput $input): NodeResult
    {
        // Move to Trash rather than permanently delete: trashing only needs the
        // gmail.modify scope the credential requests, and it is reversible.
        // Permanent deletion would require the full https://mail.google.com/ scope.
        $messageId = $input->config['message_id'];
        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post("/users/me/messages/{$messageId}/trash");

        return $response->successful()
            ? $this->success(['trashed' => true, 'message_id' => $messageId])
            : $this->fail("Gmail delete_message failed: {$response->body()}");
    }

    private function createDraft(NodeInput $input): NodeResult
    {
        $to = $input->config['to'];
        $subject = $input->config['subject'] ?? '';
        $body = $input->config['body'] ?? '';
        $raw = base64_encode("To: {$to}\r\nSubject: {$subject}\r\n\r\n{$body}");

        $response = $this->httpWithAuth($input, self::BASE_URL)
            ->post('/users/me/drafts', ['message' => ['raw' => strtr($raw, '+/', '-_')]]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Gmail create_draft failed: {$response->body()}");
    }
}
