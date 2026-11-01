<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\Outlook\OutlookCopyMessageNode;
use App\Nodes\Integrations\Outlook\OutlookCreateFolderNode;
use App\Nodes\Integrations\Outlook\OutlookFlagMessageNode;
use App\Nodes\Integrations\Outlook\OutlookForwardMessageNode;
use App\Nodes\Integrations\Outlook\OutlookGetAttachmentNode;
use App\Nodes\Integrations\Outlook\OutlookGetAutoReplyNode;
use App\Nodes\Integrations\Outlook\OutlookGetProfileNode;
use App\Nodes\Integrations\Outlook\OutlookListAttachmentsNode;
use App\Nodes\Integrations\Outlook\OutlookListFoldersNode;
use App\Nodes\Integrations\Outlook\OutlookMarkReadNode;
use App\Nodes\Integrations\Outlook\OutlookReplyAllNode;
use App\Nodes\Integrations\Outlook\OutlookSendDraftNode;
use App\Nodes\Integrations\Outlook\OutlookSetAutoReplyNode;
use App\Nodes\Integrations\Outlook\OutlookSetCategoriesNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const OUTLOOK_EXTRA = ['access_token' => 'EwB-test'];

/**
 * Answer the API from a fresh fake, so a test can change its answers part-way.
 *
 * @param  array<string, mixed>  $stubs
 */
function outlookExtraFake(array $stubs): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
}

/**
 * The JSON body of the one call made with a method to a path.
 *
 * @return array<string, mixed>
 */
function outlookExtraBody(string $method, string $suffix): array
{
    $found = array_values(array_filter(Http::recorded()->all(), fn (array $pair): bool => $pair[0]->method() === $method && str_ends_with((string) parse_url($pair[0]->url(), PHP_URL_PATH), $suffix)));

    expect($found)->toHaveCount(1);

    return (array) json_decode($found[0][0]->body(), true);
}

it('registers every extra outlook node under the outlook category', function () {
    foreach (['mark_read', 'flag_message', 'set_categories', 'forward_message', 'reply_all', 'copy_message', 'send_draft', 'list_attachments', 'get_attachment', 'list_folders', 'create_folder', 'get_auto_reply', 'set_auto_reply', 'get_profile'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("outlook_{$suffix}")->category())->toBe('outlook');
    }
});

it('marks a message read or unread and flags it', function () {
    outlookExtraFake(['graph.microsoft.com/*' => Http::response(['id' => 'AAMk1'])]);
    $run = Run::factory()->create();

    $unread = (new OutlookMarkReadNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'state' => 'unread'], []);
    expect($unread)->toBe(['message_id' => 'AAMk1', 'is_read' => false])->and(outlookExtraBody('PATCH', '/me/messages/AAMk1'))->toBe(['isRead' => false]);

    outlookExtraFake(['graph.microsoft.com/*' => Http::response(['id' => 'AAMk1'])]);
    (new OutlookFlagMessageNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'flag' => 'complete'], []);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && ($request['flag'] ?? null) === ['flagStatus' => 'complete']);
});

it('adds, removes and replaces categories', function () {
    $run = Run::factory()->create();
    $node = new OutlookSetCategoriesNode;
    $stub = fn () => outlookExtraFake(['graph.microsoft.com/*' => fn (Request $request) => Http::response($request->method() === 'GET' ? ['categories' => ['Red category', 'Blue category']] : [])]);

    $stub();
    $added = $node->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'categories' => ['Green category', 'Red category'], 'mode' => 'add'], []);
    expect($added['categories'])->toBe(['Red category', 'Blue category', 'Green category'])->and(outlookExtraBody('PATCH', '/me/messages/AAMk1'))->toBe(['categories' => ['Red category', 'Blue category', 'Green category']]);

    $stub();
    expect($node->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'categories' => 'Red category', 'mode' => 'remove'], [])['categories'])->toBe(['Blue category']);

    $stub();
    expect($node->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'categories' => ['Only one'], 'mode' => 'replace'], [])['categories'])->toBe(['Only one'])
        ->and(fn () => $node->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'categories' => [], 'mode' => 'add'], []))->toThrow(InvalidArgumentException::class, 'at least one category');
});

it('forwards, replies to all, copies and sends a draft', function () {
    outlookExtraFake(['graph.microsoft.com/*' => Http::response(null, 202)]);
    $run = Run::factory()->create();

    $forwarded = (new OutlookForwardMessageNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'to' => 'bob@example.com, Carol <carol@example.com>', 'comment' => 'FYI'], []);
    expect($forwarded)->toBe(['forwarded' => true, 'message_id' => 'AAMk1', 'to' => ['bob@example.com', 'carol@example.com']])
        ->and(outlookExtraBody('POST', '/me/messages/AAMk1/forward'))->toBe(['comment' => 'FYI', 'toRecipients' => [['emailAddress' => ['address' => 'bob@example.com']], ['emailAddress' => ['address' => 'carol@example.com']]]])
        ->and(fn () => (new OutlookForwardMessageNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'to' => ''], []))->toThrow(InvalidArgumentException::class, 'At least one recipient');

    expect((new OutlookReplyAllNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'comment' => 'Thanks all'], []))->toBe(['replied' => true, 'message_id' => 'AAMk1'])
        ->and((new OutlookSendDraftNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk9'], []))->toBe(['sent' => true, 'message_id' => 'AAMk9'])
        ->and(fn () => (new OutlookReplyAllNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'comment' => ' '], []))->toThrow(InvalidArgumentException::class, 'The reply is required');

    outlookExtraFake(['graph.microsoft.com/*' => Http::response(['id' => 'AAMk2', 'parentFolderId' => 'folder-archive'])]);
    $copied = (new OutlookCopyMessageNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'folder' => 'archive'], []);

    expect($copied)->toBe(['copied' => true, 'message_id' => 'AAMk2', 'original_id' => 'AAMk1', 'folder_id' => 'folder-archive'])
        ->and(outlookExtraBody('POST', '/me/messages/AAMk1/copy'))->toBe(['destinationId' => 'archive']);
});

it('lists attachments and downloads one as base64', function () {
    outlookExtraFake([
        'graph.microsoft.com/v1.0/me/messages/AAMk1/attachments/att1' => Http::response(['id' => 'att1', 'name' => 'a.pdf', 'contentType' => 'application/pdf', 'size' => 4, 'contentBytes' => base64_encode('%PDF')]),
        'graph.microsoft.com/v1.0/me/messages/AAMk1/attachments*' => Http::response(['value' => [['id' => 'att1', 'name' => 'a.pdf', 'contentType' => 'application/pdf', 'size' => 4, 'isInline' => false]]]),
    ]);
    $run = Run::factory()->create();

    $listed = (new OutlookListAttachmentsNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1'], []);
    $file = (new OutlookGetAttachmentNode)->execute($run, [...OUTLOOK_EXTRA, 'message_id' => 'AAMk1', 'attachment_id' => 'att1'], []);

    expect($listed)->toBe(['attachments' => [['id' => 'att1', 'name' => 'a.pdf', 'content_type' => 'application/pdf', 'size' => 4, 'inline' => false]], 'count' => 1])
        ->and($file)->toBe(['id' => 'att1', 'name' => 'a.pdf', 'content_type' => 'application/pdf', 'size' => 4, 'content_base64' => base64_encode('%PDF')]);
});

it('lists folders, top level or inside one, and creates them', function () {
    outlookExtraFake(['graph.microsoft.com/*' => fn (Request $request) => $request->method() === 'POST'
        ? Http::response(['id' => 'f2', 'displayName' => 'Acme', 'parentFolderId' => 'f1'])
        : Http::response(['value' => [['id' => 'f1', 'displayName' => 'Clients', 'unreadItemCount' => 2, 'totalItemCount' => 9, 'childFolderCount' => 1]]])]);
    $run = Run::factory()->create();

    $folders = (new OutlookListFoldersNode)->execute($run, [...OUTLOOK_EXTRA, 'parent' => 'inbox'], []);
    $created = (new OutlookCreateFolderNode)->execute($run, [...OUTLOOK_EXTRA, 'name' => 'Acme', 'parent' => 'f1'], []);

    expect($folders['folders'])->toBe([['id' => 'f1', 'name' => 'Clients', 'unread' => 2, 'total' => 9, 'children' => 1]])
        ->and($created)->toBe(['id' => 'f2', 'name' => 'Acme', 'parent_id' => 'f1'])
        ->and(outlookExtraBody('POST', '/me/mailFolders/f1/childFolders'))->toBe(['displayName' => 'Acme']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/me/mailFolders/inbox/childFolders'));
});

it('reads and sets the automatic replies, scheduled in UTC', function () {
    outlookExtraFake(['graph.microsoft.com/*' => Http::response(['status' => 'scheduled', 'externalAudience' => 'all', 'internalReplyMessage' => 'Away', 'externalReplyMessage' => 'Away', 'scheduledStartDateTime' => ['dateTime' => '2026-03-01T00:00:00.0000000', 'timeZone' => 'UTC'], 'scheduledEndDateTime' => ['dateTime' => '2026-03-08T00:00:00.0000000', 'timeZone' => 'UTC']])]);
    $run = Run::factory()->create();

    expect((new OutlookGetAutoReplyNode)->execute($run, OUTLOOK_EXTRA, []))->toMatchArray(['status' => 'scheduled', 'internal_message' => 'Away', 'starts_at' => '2026-03-01T00:00:00.0000000']);

    outlookExtraFake(['graph.microsoft.com/*' => Http::response(null, 200)]);
    $node = new OutlookSetAutoReplyNode;
    $node->execute($run, [...OUTLOOK_EXTRA, 'status' => 'scheduled', 'internal_message' => 'Away until the 8th', 'starts_at' => '2026-03-01T00:00:00Z', 'ends_at' => '2026-03-08T00:00:00Z', 'external_audience' => 'contactsOnly'], []);

    expect(outlookExtraBody('PATCH', '/me/mailboxSettings'))->toBe(['automaticRepliesSetting' => [
        'status' => 'scheduled', 'externalAudience' => 'contactsOnly', 'internalReplyMessage' => 'Away until the 8th', 'externalReplyMessage' => 'Away until the 8th',
        'scheduledStartDateTime' => ['dateTime' => '2026-03-01T00:00:00', 'timeZone' => 'UTC'], 'scheduledEndDateTime' => ['dateTime' => '2026-03-08T00:00:00', 'timeZone' => 'UTC'],
    ]])
        ->and(fn () => $node->execute($run, [...OUTLOOK_EXTRA, 'status' => 'alwaysEnabled'], []))->toThrow(InvalidArgumentException::class, 'Write the reply')
        ->and(fn () => $node->execute($run, [...OUTLOOK_EXTRA, 'status' => 'scheduled', 'internal_message' => 'x'], []))->toThrow(InvalidArgumentException::class, 'needs a start and an end')
        ->and(fn () => $node->execute($run, [...OUTLOOK_EXTRA, 'status' => 'scheduled', 'internal_message' => 'x', 'starts_at' => '2026-03-08T00:00:00Z', 'ends_at' => '2026-03-01T00:00:00Z'], []))->toThrow(InvalidArgumentException::class, 'before the start');
});

it('turns the automatic reply off without asking for a message, and reads the profile', function () {
    outlookExtraFake(['graph.microsoft.com/v1.0/me?*' => Http::response(['id' => 'u1', 'displayName' => 'Ada', 'mail' => null, 'userPrincipalName' => 'ada@contoso.com']), 'graph.microsoft.com/*' => Http::response(null, 200)]);
    $run = Run::factory()->create();

    (new OutlookSetAutoReplyNode)->execute($run, [...OUTLOOK_EXTRA, 'status' => 'disabled'], []);

    expect(outlookExtraBody('PATCH', '/me/mailboxSettings'))->toBe(['automaticRepliesSetting' => ['status' => 'disabled']])
        ->and((new OutlookGetProfileNode)->execute($run, OUTLOOK_EXTRA, []))->toBe(['id' => 'u1', 'name' => 'Ada', 'email' => 'ada@contoso.com', 'user_principal_name' => 'ada@contoso.com']);
});
