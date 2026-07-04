<?php

namespace App\Engine\Nodes\Apps\Telegram;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class TelegramNode extends AppNode
{
    public const TYPE = 'telegram';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send_message' => $this->sendMessage($input),
            'send_photo' => $this->sendPhoto($input),
            'send_document' => $this->sendDocument($input),
            'get_updates' => $this->getUpdates($input),
            'edit_message' => $this->editMessage($input),
            'delete_message' => $this->deleteMessage($input),
            default => $this->fail("Telegram: unknown operation '{$operation}'"),
        };
    }

    private function telegramHttp(NodeInput $input): PendingRequest
    {
        $token = $input->credentials['bot_token'] ?? $input->credentials['api_key'] ?? '';

        return $this->http()->baseUrl("https://api.telegram.org/bot{$token}");
    }

    private function sendMessage(NodeInput $input): NodeResult
    {
        $response = $this->telegramHttp($input)->post('/sendMessage', array_filter([
            'chat_id' => $input->config['chat_id'],
            'text' => $input->config['text'] ?? '',
            'parse_mode' => $input->config['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => $input->config['disable_preview'] ?? null,
            'reply_to_message_id' => $input->config['reply_to_message_id'] ?? null,
        ]));

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['message_id' => $data['result']['message_id'], 'sent' => true])
            : $this->fail('Telegram send_message error: '.($data['description'] ?? $response->body()));
    }

    private function sendPhoto(NodeInput $input): NodeResult
    {
        $response = $this->telegramHttp($input)->post('/sendPhoto', array_filter([
            'chat_id' => $input->config['chat_id'],
            'photo' => $input->config['photo'],
            'caption' => $input->config['caption'] ?? null,
            'parse_mode' => $input->config['parse_mode'] ?? 'HTML',
        ]));

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['message_id' => $data['result']['message_id'], 'sent' => true])
            : $this->fail('Telegram send_photo error: '.($data['description'] ?? $response->body()));
    }

    private function sendDocument(NodeInput $input): NodeResult
    {
        $response = $this->telegramHttp($input)->post('/sendDocument', array_filter([
            'chat_id' => $input->config['chat_id'],
            'document' => $input->config['document'],
            'caption' => $input->config['caption'] ?? null,
            'parse_mode' => $input->config['parse_mode'] ?? 'HTML',
        ]));

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['message_id' => $data['result']['message_id'], 'sent' => true])
            : $this->fail('Telegram send_document error: '.($data['description'] ?? $response->body()));
    }

    private function getUpdates(NodeInput $input): NodeResult
    {
        $response = $this->telegramHttp($input)->get('/getUpdates', array_filter([
            'offset' => $input->config['offset'] ?? null,
            'limit' => $input->config['limit'] ?? 10,
            'timeout' => $input->config['timeout'] ?? 0,
        ]));

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['updates' => $data['result'] ?? []])
            : $this->fail('Telegram get_updates error: '.($data['description'] ?? $response->body()));
    }

    private function editMessage(NodeInput $input): NodeResult
    {
        $response = $this->telegramHttp($input)->post('/editMessageText', array_filter([
            'chat_id' => $input->config['chat_id'],
            'message_id' => $input->config['message_id'],
            'text' => $input->config['text'] ?? '',
            'parse_mode' => $input->config['parse_mode'] ?? 'HTML',
        ]));

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['edited' => true])
            : $this->fail('Telegram edit_message error: '.($data['description'] ?? $response->body()));
    }

    private function deleteMessage(NodeInput $input): NodeResult
    {
        $response = $this->telegramHttp($input)->post('/deleteMessage', [
            'chat_id' => $input->config['chat_id'],
            'message_id' => $input->config['message_id'],
        ]);

        $data = $response->json();

        return ($data['ok'] ?? false)
            ? $this->success(['deleted' => true])
            : $this->fail('Telegram delete_message error: '.($data['description'] ?? $response->body()));
    }
}
