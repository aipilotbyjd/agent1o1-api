<?php

namespace App\Engine\Nodes\Apps\Mail;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Mail;

class MailNode extends AppNode
{
    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'send' => $this->send($input),
            default => $this->fail("Mail: unknown operation '{$operation}'"),
        };
    }

    private function send(NodeInput $input): NodeResult
    {
        $to = $input->config['to'];
        $subject = $input->config['subject'] ?? '';
        $body = $input->config['body'] ?? '';
        $isHtml = (bool) ($input->config['is_html'] ?? false);

        Mail::send([], [], function ($message) use ($to, $subject, $body, $isHtml) {
            $message->to($to)->subject($subject);

            if ($isHtml) {
                $message->html($body);
            } else {
                $message->text($body);
            }
        });

        return $this->success(['sent' => true, 'to' => $to]);
    }
}
