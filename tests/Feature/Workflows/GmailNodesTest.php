<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\GmailAddLabelNode;
use App\Nodes\Integrations\Gmail\GmailCreateDraftNode;
use App\Nodes\Integrations\Gmail\GmailCreateLabelNode;
use App\Nodes\Integrations\Gmail\GmailDeleteMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetAttachmentNode;
use App\Nodes\Integrations\Gmail\GmailGetMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetThreadNode;
use App\Nodes\Integrations\Gmail\GmailListLabelsNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesNode;
use App\Nodes\Integrations\Gmail\GmailListMessagesWithAttachmentsNode;
use App\Nodes\Integrations\Gmail\GmailModifyMessageNode;
use App\Nodes\Integrations\Gmail\GmailReplyToMessageNode;
use App\Nodes\Integrations\Gmail\GmailSendDraftNode;
use App\Nodes\Integrations\Gmail\GmailSendEmailNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

it('registers every gmail node under its own type string', function () {
    $registry = app(NodeRegistry::class);

    $types = [
        'gmail_send_email' => GmailSendEmailNode::class,
        'gmail_list_messages' => GmailListMessagesNode::class,
        'gmail_get_message' => GmailGetMessageNode::class,
        'gmail_reply_to_message' => GmailReplyToMessageNode::class,
        'gmail_create_draft' => GmailCreateDraftNode::class,
        'gmail_delete_message' => GmailDeleteMessageNode::class,
        'gmail_modify_message' => GmailModifyMessageNode::class,
        'gmail_add_label' => GmailAddLabelNode::class,
        'gmail_list_labels' => GmailListLabelsNode::class,
    ];

    foreach ($types as $type => $class) {
        expect($registry->has($type))->toBeTrue();
        $node = $registry->resolve($type);
        expect($node)->toBeInstanceOf($class);
        expect($node->category())->toBe('gmail');
    }
});

it('sends an email with a base64url-encoded raw message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'threadId' => 't1'])]);

    $node = new GmailSendEmailNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'subject' => 'Hi',
        'body' => 'Hello there',
    ], []);

    expect($output)->toBe(['id' => 'm1', 'threadId' => 't1']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            && $request->hasHeader('Authorization', 'Bearer ya29-test')
            && ! str_contains($request['raw'], '+')
            && ! str_contains($request['raw'], '/');
    });
});

it('throws when gmail answers a non-2xx status', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid Credentials']], 401)]);

    $node = new GmailSendEmailNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'subject' => 'Hi',
        'body' => 'Hello there',
    ], []))->toThrow(RuntimeException::class, 'Invalid Credentials');
});

it('throws when access_token is missing', function () {
    $node = new GmailListLabelsNode;
    $run = Run::factory()->create();

    expect(fn () => $node->execute($run, [], []))->toThrow(RuntimeException::class, 'access_token');
});

it('lists messages with the query and max_results as query params', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['messages' => []])]);

    $node = new GmailListMessagesNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'query' => 'is:unread', 'max_results' => 5], []);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/users/me/messages')
            && $request['q'] === 'is:unread'
            && $request['maxResults'] == 5;
    });
});

it('gets a message by id', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'snippet' => 'hi'])]);

    $node = new GmailGetMessageNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'message_id' => 'm1'], []);

    expect($output)->toBe(['id' => 'm1', 'snippet' => 'hi']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/messages/m1'));
});

it('replies to a message within the same thread', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm2', 'threadId' => 't1'])]);

    $node = new GmailReplyToMessageNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'message_id' => 'm1',
        'thread_id' => 't1',
        'body' => 'reply body',
    ], []);

    Http::assertSent(fn ($request) => ($request['threadId'] ?? null) === 't1');
});

it('creates a draft', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'd1'])]);

    $node = new GmailCreateDraftNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [
        'access_token' => 'ya29-test',
        'to' => 'a@example.com',
        'subject' => 'Draft',
        'body' => 'draft body',
    ], []);

    expect($output)->toBe(['id' => 'd1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/drafts') && isset($request['message']['raw']));
});

it('deletes (trashes) a message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    $node = new GmailDeleteMessageNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test', 'message_id' => 'm1'], []);

    expect($output)->toBe(['trashed' => true, 'message_id' => 'm1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/messages/m1/trash'));
});

it('modifies labels on a message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    $node = new GmailModifyMessageNode;
    $run = Run::factory()->create();

    $node->execute($run, [
        'access_token' => 'ya29-test',
        'message_id' => 'm1',
        'add_label_ids' => ['STARRED'],
        'remove_label_ids' => ['UNREAD'],
    ], []);

    Http::assertSent(fn ($request) => $request['addLabelIds'] === ['STARRED'] && $request['removeLabelIds'] === ['UNREAD']);
});

it('adds a label to a message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    $node = new GmailAddLabelNode;
    $run = Run::factory()->create();

    $node->execute($run, ['access_token' => 'ya29-test', 'message_id' => 'm1', 'label_ids' => ['IMPORTANT']], []);

    Http::assertSent(fn ($request) => $request['addLabelIds'] === ['IMPORTANT'] && $request['removeLabelIds'] === []);
});

it('lists labels', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['labels' => [['id' => 'INBOX']]])]);

    $node = new GmailListLabelsNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, ['access_token' => 'ya29-test'], []);

    expect($output['labels'])->toBe([['id' => 'INBOX']]);
});

const GMAIL_BASE = ['access_token' => 'ya29-test'];

/**
 * The raw message a request carried, decoded from base64url.
 */
function decodedRaw(string $raw): string
{
    return base64_decode(strtr($raw, '-_', '+/'));
}

/**
 * The body of a single-part message, decoded from its base64 transfer encoding.
 */
function decodedBody(string $message): string
{
    return base64_decode(preg_replace('/\s+/', '', explode("\r\n\r\n", $message, 2)[1]));
}

it('registers the new gmail nodes', function () {
    foreach (['get_thread', 'send_draft', 'create_label', 'get_attachment'] as $suffix) {
        expect(app(NodeRegistry::class)->has("gmail_{$suffix}"))->toBeTrue();
    }
});

it('sends with cc, bcc and reply-to, and keeps the body intact in any language', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    (new GmailSendEmailNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE,
        'to' => 'a@example.com, b@example.com',
        'cc' => 'c@example.com',
        'bcc' => 'd@example.com',
        'reply_to' => 'boss@example.com',
        'subject' => 'Hi',
        'body' => "Namaste नमस्ते\r\nLine two",
    ], []);

    Http::assertSent(function ($request) {
        $message = decodedRaw($request['raw']);

        return str_contains($message, "To: a@example.com, b@example.com\r\n")
            && str_contains($message, "Cc: c@example.com\r\n")
            && str_contains($message, "Bcc: d@example.com\r\n")
            && str_contains($message, "Reply-To: boss@example.com\r\n")
            && str_contains($message, "Subject: Hi\r\n")
            && str_contains($message, 'Content-Type: text/plain; charset=UTF-8')
            && decodedBody($message) === "Namaste नमस्ते\r\nLine two"
            && ! str_contains($request['raw'], '=');
    });
});

it('encodes a non-english subject and cannot be made to add headers', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    (new GmailSendEmailNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'to' => "a@example.com\r\nBcc: evil@example.com", 'subject' => 'Café रिपोर्ट', 'body' => 'x', 'is_html' => true,
    ], []);

    Http::assertSent(function ($request) {
        $message = decodedRaw($request['raw']);

        return str_contains($message, 'Subject: =?UTF-8?B?')
            && ! str_contains($message, "\r\nBcc: evil@example.com")
            && str_contains($message, 'Content-Type: text/html; charset=UTF-8');
    });
});

it('sends attachments as a multipart message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'])]);

    (new GmailSendEmailNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'to' => 'a@example.com', 'subject' => 'Report', 'body' => 'See attached',
        'attachments' => [
            ['filename' => 'report.csv', 'content' => "a,b\n1,2", 'mime_type' => 'text/csv'],
            ['filename' => 'logo.bin', 'content_base64' => base64_encode("\x00\x01")],
        ],
    ], []);

    Http::assertSent(function ($request) {
        $message = decodedRaw($request['raw']);

        return str_contains($message, 'Content-Type: multipart/mixed; boundary=')
            && str_contains($message, 'Content-Disposition: attachment; filename="report.csv"')
            && str_contains($message, base64_encode("a,b\n1,2"))
            && str_contains($message, 'Content-Type: application/octet-stream; name="logo.bin"')
            && str_contains($message, base64_encode("\x00\x01"));
    });
});

it('refuses an attachment without a name or content', function () {
    Http::fake();

    (new GmailSendEmailNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'to' => 'a@example.com', 'subject' => 'x', 'body' => 'x', 'attachments' => [['filename' => 'empty.txt']],
    ], []);
})->throws(InvalidArgumentException::class, 'filename and its content');

it('puts cc and attachments on a draft too', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'd1'])]);

    (new GmailCreateDraftNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'to' => 'a@example.com', 'cc' => 'c@example.com', 'subject' => 'Draft', 'body' => 'x',
        'attachments' => [['filename' => 'n.txt', 'content' => 'note']],
    ], []);

    Http::assertSent(fn ($request) => str_contains(decodedRaw($request['message']['raw']), "Cc: c@example.com\r\n")
        && str_contains(decodedRaw($request['message']['raw']), 'filename="n.txt"'));
});

it('threads a reply with the original Message-ID header, not the gmail id', function () {
    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages/m1?*' => Http::response(['payload' => ['headers' => [
            ['name' => 'Message-ID', 'value' => '<abc@mail.example.com>'],
            ['name' => 'References', 'value' => '<first@mail.example.com>'],
        ]]]),
        'gmail.googleapis.com/*' => Http::response(['id' => 'm2', 'threadId' => 't1']),
    ]);

    (new GmailReplyToMessageNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'to' => 'a@example.com', 'message_id' => 'm1', 'thread_id' => 't1', 'subject' => 'Re: Plan', 'body' => 'ok',
    ], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'metadataHeaders=Message-ID&metadataHeaders=References'));
    Http::assertSent(function ($request) {
        if (! isset($request['raw'])) {
            return false;
        }

        $message = decodedRaw($request['raw']);

        return $request['threadId'] === 't1'
            && str_contains($message, "In-Reply-To: <abc@mail.example.com>\r\n")
            && str_contains($message, "References: <first@mail.example.com> <abc@mail.example.com>\r\n")
            && str_contains($message, "Subject: Re: Plan\r\n")
            && ! str_contains($message, 'Re: Re:');
    });
});

it('adds Re: to a reply subject that lacks it and still replies when the original has no Message-ID', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm2'])]);

    (new GmailReplyToMessageNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'to' => 'a@example.com', 'message_id' => 'm1', 'thread_id' => 't1', 'subject' => 'Plan', 'body' => 'ok',
    ], []);

    Http::assertSent(function ($request) {
        if (! isset($request['raw'])) {
            return false;
        }

        $message = decodedRaw($request['raw']);

        return str_contains($message, "Subject: Re: Plan\r\n") && ! str_contains($message, 'In-Reply-To');
    });
});

it('reads a thread', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 't1', 'messages' => [['id' => 'm1'], ['id' => 'm2']]])]);

    $output = (new GmailGetThreadNode)->execute(Run::factory()->create(), [...GMAIL_BASE, 'thread_id' => 't1'], []);

    expect($output['messages'])->toHaveCount(2);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/users/me/threads/t1'));
});

it('sends a draft by id', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm9', 'threadId' => 't9'])]);

    (new GmailSendDraftNode)->execute(Run::factory()->create(), [...GMAIL_BASE, 'draft_id' => 'r-1'], []);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/users/me/drafts/send') && $request['id'] === 'r-1');
});

it('creates a label with visibility settings', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'Label_9', 'name' => 'Clients/Acme'])]);

    (new GmailCreateLabelNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'name' => 'Clients/Acme', 'show_in_message_list' => false, 'show_in_label_list' => 'labelShowIfUnread',
    ], []);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/users/me/labels')
        && $request['name'] === 'Clients/Acme'
        && $request['messageListVisibility'] === 'hide'
        && $request['labelListVisibility'] === 'labelShowIfUnread');
});

it('returns an attachment as ordinary base64', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['size' => 3, 'data' => strtr(base64_encode("\xfb\xff\xfe"), '+/', '-_')])]);

    $output = (new GmailGetAttachmentNode)->execute(Run::factory()->create(), [...GMAIL_BASE, 'message_id' => 'm1', 'attachment_id' => 'att/1'], []);

    expect($output)->toBe(['size' => 3, 'content_base64' => base64_encode("\xfb\xff\xfe")]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/users/me/messages/m1/attachments/att%2F1'));
});

it('lists only messages in the chosen labels, repeating the label parameter as Gmail wants', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['messages' => []])]);

    (new GmailListMessagesNode)->execute(Run::factory()->create(), [
        ...GMAIL_BASE, 'query' => 'is:unread', 'label_ids' => ['INBOX', 'Label_9'], 'max_results' => 7, 'include_spam_trash' => true,
    ], []);

    Http::assertSent(fn ($request) => str_ends_with(explode('?', $request->url())[0], '/users/me/messages')
        && str_contains($request->url(), 'q=is%3Aunread')
        && str_contains($request->url(), 'maxResults=7')
        && str_contains($request->url(), 'includeSpamTrash=true')
        && str_contains($request->url(), '&labelIds=INBOX&labelIds=Label_9'));
});

it('lists without label or spam parameters by default', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['messages' => []])]);

    (new GmailListMessagesNode)->execute(Run::factory()->create(), GMAIL_BASE, []);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'labelIds') && ! str_contains($request->url(), 'includeSpamTrash') && ! str_contains($request->url(), 'q='));
});

it('gives a new email trigger its body text and attachments, not just a snippet', function () {
    $encode = fn (string $text): string => rtrim(strtr(base64_encode($text), '+/', '-_'), '=');

    Http::fake(['gmail.googleapis.com/*' => Http::response([
        'id' => 'm1', 'threadId' => 't1', 'snippet' => 'Hi', 'labelIds' => ['INBOX', 'UNREAD'],
        'payload' => [
            'mimeType' => 'multipart/mixed',
            'headers' => [
                ['name' => 'From', 'value' => 'Ada <ada@example.com>'], ['name' => 'To', 'value' => 'me@example.com'], ['name' => 'Cc', 'value' => 'bob@example.com'],
                ['name' => 'Subject', 'value' => 'Invoice'], ['name' => 'Date', 'value' => 'Mon, 1 Mar 2026 10:00:00 +0000'], ['name' => 'Message-ID', 'value' => '<abc@mail>'],
            ],
            'parts' => [
                ['mimeType' => 'multipart/alternative', 'parts' => [
                    ['mimeType' => 'text/plain', 'body' => ['data' => $encode("Please pay नमस्ते\n")]],
                    ['mimeType' => 'text/html', 'body' => ['data' => $encode('<p>Please pay</p>')]],
                ]],
                ['mimeType' => 'application/pdf', 'filename' => 'invoice.pdf', 'body' => ['attachmentId' => 'att1', 'size' => 2048]],
                ['mimeType' => 'image/png', 'filename' => '', 'body' => ['data' => $encode('inline')]],
            ],
        ],
    ])]);

    $item = (new GmailListMessagesNode)->expandTriggerItem(Run::factory()->create(), GMAIL_BASE, ['id' => 'm1']);

    expect($item)->toMatchArray([
        'id' => 'm1', 'thread_id' => 't1', 'message_id' => '<abc@mail>', 'from' => 'Ada <ada@example.com>', 'cc' => 'bob@example.com',
        'subject' => 'Invoice', 'body_text' => "Please pay नमस्ते\n", 'body_html' => '<p>Please pay</p>', 'has_attachments' => true, 'is_unread' => true,
    ])->and($item['attachments'])->toBe([['attachment_id' => 'att1', 'filename' => 'invoice.pdf', 'mime_type' => 'application/pdf', 'size' => 2048]]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/users/me/messages/m1?format=full'));
});

it('falls back to the html as text for an html-only email', function () {
    $encode = fn (string $text): string => rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm2', 'payload' => ['mimeType' => 'text/html', 'body' => ['data' => $encode('<style>p{}</style><p>Fish &amp; chips</p>')]]])]);

    $item = (new GmailListMessagesNode)->expandTriggerItem(Run::factory()->create(), GMAIL_BASE, ['id' => 'm2']);

    expect($item['body_text'])->toBe('Fish & chips')->and($item['attachments'])->toBe([])->and($item['has_attachments'])->toBeFalse();
});

it('limits an enormous body', function () {
    $encode = fn (string $text): string => rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm3', 'payload' => ['mimeType' => 'text/plain', 'body' => ['data' => $encode(str_repeat('a', 150000))]]])]);

    $item = (new GmailListMessagesNode)->expandTriggerItem(Run::factory()->create(), GMAIL_BASE, ['id' => 'm3']);

    expect(mb_strlen($item['body_text']))->toBe(100000);
});

it('lists messages with attachments by adding has:attachment to the search', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['messages' => []])]);
    $node = new GmailListMessagesWithAttachmentsNode;

    $node->execute(Run::factory()->create(), [...GMAIL_BASE, 'query' => 'from:billing@example.com'], []);
    $node->execute(Run::factory()->create(), GMAIL_BASE, []);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=has:attachment from:billing@example.com'));
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=has:attachment') && ! str_contains($request->url(), 'from'));
    expect($node->triggerName())->toBe('Gmail: New email with attachment')
        ->and(app(NodeRegistry::class)->has('gmail_list_messages_with_attachments'))->toBeTrue();
});
