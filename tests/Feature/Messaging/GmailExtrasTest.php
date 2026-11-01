<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\GmailArchiveMessageNode;
use App\Nodes\Integrations\Gmail\GmailBatchModifyMessagesNode;
use App\Nodes\Integrations\Gmail\GmailDeleteDraftNode;
use App\Nodes\Integrations\Gmail\GmailDeleteLabelNode;
use App\Nodes\Integrations\Gmail\GmailForwardMessageNode;
use App\Nodes\Integrations\Gmail\GmailGetDraftNode;
use App\Nodes\Integrations\Gmail\GmailGetProfileNode;
use App\Nodes\Integrations\Gmail\GmailListDraftsNode;
use App\Nodes\Integrations\Gmail\GmailMarkReadNode;
use App\Nodes\Integrations\Gmail\GmailTrashMessageNode;
use App\Nodes\Integrations\Gmail\GmailTrashThreadNode;
use App\Nodes\Integrations\Gmail\GmailUntrashMessageNode;
use App\Nodes\Integrations\Gmail\GmailUpdateDraftNode;
use App\Nodes\Integrations\Gmail\GmailUpdateLabelNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const GMAIL_EXTRA = ['access_token' => 'ya29-test'];

/**
 * Answer the API from a fresh fake, so a test can change its answers part-way.
 *
 * @param  array<string, mixed>  $stubs
 */
function gmailExtraFake(array $stubs): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
}

/**
 * The JSON body of the one call made to a path.
 *
 * @return array<string, mixed>
 */
function gmailExtraBody(string $method, string $suffix): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method() === $method && str_ends_with((string) parse_url($pair[0]->url(), PHP_URL_PATH), $suffix)));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

it('registers every extra gmail node under the gmail category', function () {
    $types = ['trash_message', 'untrash_message', 'mark_read', 'archive_message', 'batch_modify_messages', 'forward_message', 'trash_thread', 'update_label', 'delete_label', 'list_drafts', 'get_draft', 'update_draft', 'delete_draft', 'get_profile'];

    foreach ($types as $suffix) {
        expect(app(NodeRegistry::class)->resolve("gmail_{$suffix}")->category())->toBe('gmail');
    }
});

it('trashes and restores a message', function () {
    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'labelIds' => ['TRASH']])]);

    $trashed = (new GmailTrashMessageNode)->execute(Run::factory()->create(), [...GMAIL_EXTRA, 'message_id' => 'm1'], []);
    $restored = (new GmailUntrashMessageNode)->execute(Run::factory()->create(), [...GMAIL_EXTRA, 'message_id' => 'm1'], []);

    expect($trashed)->toBe(['trashed' => true, 'message_id' => 'm1', 'label_ids' => ['TRASH']])->and($restored['restored'])->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/users/me/messages/m1/trash'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/me/messages/m1/untrash'));
});

it('marks read or unread and archives through the UNREAD and INBOX labels', function () {
    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'labelIds' => []])]);
    $run = Run::factory()->create();

    (new GmailMarkReadNode)->execute($run, [...GMAIL_EXTRA, 'message_id' => 'm1'], []);
    expect(gmailExtraBody('POST', '/messages/m1/modify'))->toBe(['removeLabelIds' => ['UNREAD']]);

    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'labelIds' => ['UNREAD']])]);
    $unread = (new GmailMarkReadNode)->execute($run, [...GMAIL_EXTRA, 'message_id' => 'm1', 'state' => 'unread'], []);
    expect($unread['is_read'])->toBeFalse();

    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1', 'labelIds' => []])]);
    (new GmailArchiveMessageNode)->execute($run, [...GMAIL_EXTRA, 'message_id' => 'm1'], []);
    Http::assertSent(fn (Request $request): bool => ($request['removeLabelIds'] ?? null) === ['INBOX']);
});

it('changes labels on many messages at once, after checking the list', function () {
    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(null, 204)]);
    $node = new GmailBatchModifyMessagesNode;
    $run = Run::factory()->create();

    $output = $node->execute($run, [...GMAIL_EXTRA, 'message_ids' => 'a1, b2 a1', 'remove_label_ids' => ['UNREAD']], []);

    expect($output)->toBe(['modified' => 2, 'message_ids' => ['a1', 'b2']])
        ->and(gmailExtraBody('POST', '/messages/batchModify'))->toBe(['ids' => ['a1', 'b2'], 'addLabelIds' => [], 'removeLabelIds' => ['UNREAD']])
        ->and(fn () => $node->execute($run, [...GMAIL_EXTRA, 'message_ids' => [], 'remove_label_ids' => ['UNREAD']], []))->toThrow(InvalidArgumentException::class, 'between 1 and 1000')
        ->and(fn () => $node->execute($run, [...GMAIL_EXTRA, 'message_ids' => ['a1']], []))->toThrow(InvalidArgumentException::class, 'Pick labels');
});

it('forwards the text of a message with a note, as a new email', function () {
    $original = [
        'id' => 'm1', 'threadId' => 't1', 'labelIds' => ['INBOX'], 'snippet' => 'Hi',
        'payload' => [
            'mimeType' => 'text/plain',
            'headers' => [['name' => 'From', 'value' => 'Ada <ada@example.com>'], ['name' => 'Subject', 'value' => 'Quarterly numbers'], ['name' => 'Date', 'value' => 'Mon, 1 Mar 2026 10:00:00 +0000'], ['name' => 'To', 'value' => 'me@example.com']],
            'body' => ['data' => rtrim(strtr(base64_encode('The numbers are up.'), '+/', '-_'), '='), 'size' => 19],
        ],
    ];
    gmailExtraFake(['gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'sent1', 'threadId' => 'tnew']), 'gmail.googleapis.com/*' => Http::response($original)]);

    $output = (new GmailForwardMessageNode)->execute(Run::factory()->create(), [...GMAIL_EXTRA, 'message_id' => 'm1', 'to' => 'bob@example.com', 'comment' => 'FYI'], []);
    $raw = (string) gmailExtraBody('POST', '/messages/send')['raw'];
    $mime = base64_decode(strtr($raw, '-_', '+/'));

    expect($output)->toBe(['sent' => true, 'id' => 'sent1', 'thread_id' => 'tnew', 'forwarded_message_id' => 'm1'])
        ->and($mime)->toContain('To: bob@example.com')->toContain('Subject: Fwd: Quarterly numbers');
    expect(base64_decode(preg_replace('/\s+/', '', substr($mime, strpos($mime, "\r\n\r\n") + 4))))->toContain('FYI')->toContain('Forwarded message')->toContain('From: Ada <ada@example.com>')->toContain('The numbers are up.');
});

it('trashes a conversation and reads the profile', function () {
    gmailExtraFake([
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'me@example.com', 'messagesTotal' => 10, 'threadsTotal' => 4, 'historyId' => '99']),
        'gmail.googleapis.com/*' => Http::response(['id' => 't1']),
    ]);

    expect((new GmailTrashThreadNode)->execute(Run::factory()->create(), [...GMAIL_EXTRA, 'thread_id' => 't1'], []))->toBe(['trashed' => true, 'thread_id' => 't1'])
        ->and((new GmailGetProfileNode)->execute(Run::factory()->create(), GMAIL_EXTRA, []))->toBe(['email' => 'me@example.com', 'messages_total' => 10, 'threads_total' => 4, 'history_id' => '99']);
});

it('edits and deletes a label, and needs something to change', function () {
    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(['id' => 'Label_1', 'name' => 'Clients/Acme', 'type' => 'user'])]);
    $run = Run::factory()->create();

    $updated = (new GmailUpdateLabelNode)->execute($run, [...GMAIL_EXTRA, 'label_id' => 'Label_1', 'name' => ' Clients/Acme ', 'label_visibility' => 'labelHide'], []);

    expect($updated)->toBe(['id' => 'Label_1', 'name' => 'Clients/Acme', 'type' => 'user'])
        ->and(gmailExtraBody('PATCH', '/labels/Label_1'))->toBe(['name' => 'Clients/Acme', 'labelListVisibility' => 'labelHide'])
        ->and(fn () => (new GmailUpdateLabelNode)->execute($run, [...GMAIL_EXTRA, 'label_id' => 'Label_1'], []))->toThrow(InvalidArgumentException::class, 'Change at least one thing');

    gmailExtraFake(['gmail.googleapis.com/*' => Http::response(null, 204)]);

    expect((new GmailDeleteLabelNode)->execute($run, [...GMAIL_EXTRA, 'label_id' => 'Label_1'], []))->toBe(['deleted' => true, 'label_id' => 'Label_1']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/users/me/labels/Label_1'));
});

it('lists, reads, updates and deletes drafts', function () {
    $draft = ['id' => 'd1', 'message' => ['id' => 'dm1', 'threadId' => 'dt1', 'labelIds' => ['DRAFT'], 'payload' => ['mimeType' => 'text/plain', 'headers' => [['name' => 'Subject', 'value' => 'Hello'], ['name' => 'To', 'value' => 'ada@example.com']], 'body' => ['data' => rtrim(strtr(base64_encode('Draft text'), '+/', '-_'), '='), 'size' => 10]]]];
    gmailExtraFake([
        'gmail.googleapis.com/gmail/v1/users/me/drafts?*' => Http::response(['drafts' => [['id' => 'd1', 'message' => ['id' => 'dm1', 'threadId' => 'dt1']]]]),
        'gmail.googleapis.com/gmail/v1/users/me/drafts/d1*' => fn (Request $request) => $request->method() === 'DELETE' ? Http::response(null, 204) : Http::response($draft),
    ]);
    $run = Run::factory()->create();

    $listed = (new GmailListDraftsNode)->execute($run, [...GMAIL_EXTRA, 'query' => 'subject:hello', 'limit' => 5], []);
    $read = (new GmailGetDraftNode)->execute($run, [...GMAIL_EXTRA, 'draft_id' => 'd1'], []);
    $updated = (new GmailUpdateDraftNode)->execute($run, [...GMAIL_EXTRA, 'draft_id' => 'd1', 'to' => 'ada@example.com', 'subject' => 'Hello again', 'body' => 'New text'], []);
    $deleted = (new GmailDeleteDraftNode)->execute($run, [...GMAIL_EXTRA, 'draft_id' => 'd1'], []);

    expect($listed['drafts'])->toBe([['draft_id' => 'd1', 'message_id' => 'dm1', 'thread_id' => 'dt1']])
        ->and($read)->toMatchArray(['draft_id' => 'd1', 'subject' => 'Hello', 'body_text' => 'Draft text'])
        ->and($updated)->toBe(['updated' => true, 'draft_id' => 'd1', 'message_id' => 'dm1'])
        ->and($deleted)->toBe(['deleted' => true, 'draft_id' => 'd1'])
        ->and(base64_decode(strtr((string) gmailExtraBody('PUT', '/drafts/d1')['message']['raw'], '-_', '+/')))->toContain('Subject: Hello again');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'maxResults=5') && str_contains($request->url(), 'q=subject%3Ahello'));
});

it('asks for an id instead of calling Gmail with an empty path', function () {
    Http::fake();

    expect(fn () => (new GmailTrashMessageNode)->execute(Run::factory()->create(), [...GMAIL_EXTRA, 'message_id' => ' '], []))->toThrow(InvalidArgumentException::class, 'The message is required');
    Http::assertNothingSent();
});
