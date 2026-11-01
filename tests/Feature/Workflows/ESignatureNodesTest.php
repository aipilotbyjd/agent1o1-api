<?php

use App\Contracts\SupportsTriggerMode;
use App\Enums\NodeRunStatus;
use App\Services\Workflows\NodeRegistry;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Nothing in this file may reach DocuSign or Dropbox Sign for real: an unfaked call fails the test.
    Http::preventStrayRequests();
});

const ENVELOPE_ID = '3f1b0a52-9c1e-4c64-8a52-1d2f3a4b5c6d';
const SIGN_REQUEST_ID = 'fa5c8a0b0f492d768749333ad6fcc214c111e967';
const TEMPLATE_ID = 'c26b8a16784a872da37ea946b9ddec7c1e11dff6';

/**
 * Answers DocuSign's login server (which accounts this login reaches) and the
 * account's own server with `$routes`: a pattern for the path after
 * `/accounts/{id}`, mapped to a response.
 *
 * @param  array<string, mixed>  $routes
 * @param  list<array<string, mixed>>|null  $accounts
 */
function fakeDocuSign(array $routes, ?array $accounts = null): void
{
    $accounts ??= [['account_id' => 'acc-1', 'account_name' => 'Acme', 'is_default' => true, 'base_uri' => 'https://na3.docusign.net']];

    // The most specific path first, and a trailing * so a query string still matches.
    uksort($routes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    Http::fake([
        'account.docusign.com/oauth/userinfo' => Http::response(['accounts' => $accounts]),
        ...collect($routes)->mapWithKeys(fn (mixed $response, string $path): array => ["na3.docusign.net/restapi/v2.1/accounts/acc-1{$path}*" => $response instanceof PromiseInterface ? $response : Http::response($response)])->all(),
    ]);
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function signedOutput(string $type, array $config): array
{
    $nodeRun = runNodeStep($type, ['access_token' => 'test-token', ...$config]);

    expect($nodeRun->status)->toBe(NodeRunStatus::Completed, (string) $nodeRun->error);

    return $nodeRun->output;
}

function sentTo(string $needle): Request
{
    $found = collect(Http::recorded())->map(fn (array $pair): Request => $pair[0])->first(fn (Request $request): bool => str_contains($request->url(), $needle));

    expect($found)->not->toBeNull("Nothing was sent to {$needle}");

    return $found;
}

/* ------------------------------------------------------------------ DocuSign */

it('sends a template for signature, filling each role', function () {
    fakeDocuSign(['/envelopes' => ['envelopeId' => ENVELOPE_ID, 'status' => 'sent']]);

    $output = signedOutput('docusign_send_from_template', [
        'template_id' => 'tmpl-1',
        'signers' => [['role' => 'Client', 'name' => 'Ada Lovelace', 'email' => 'ADA@Example.com'], ['role' => 'Counsel', 'name' => 'Bob', 'email' => 'bob@example.com', 'order' => 2]],
        'subject' => 'Please sign',
        'message' => 'Thanks!',
    ]);

    expect($output)->toMatchArray(['envelope_id' => ENVELOPE_ID, 'status' => 'sent', 'sent_to' => ['ada@example.com', 'bob@example.com']]);

    $request = sentTo('/envelopes');

    expect($request->header('Authorization'))->toBe(['Bearer test-token'])
        ->and($request['templateId'])->toBe('tmpl-1')
        ->and($request['status'])->toBe('sent')
        ->and($request['emailSubject'])->toBe('Please sign')
        ->and($request['templateRoles'][0])->toBe(['roleName' => 'Client', 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'routingOrder' => '1'])
        ->and($request['templateRoles'][1]['routingOrder'])->toBe('2');
});

it('can save a template as a draft instead of sending it', function () {
    fakeDocuSign(['/envelopes' => ['envelopeId' => ENVELOPE_ID, 'status' => 'created']]);

    signedOutput('docusign_send_from_template', ['template_id' => 't', 'signers' => [['role' => 'Client', 'name' => 'A', 'email' => 'a@b.com']], 'send' => false]);

    expect(sentTo('/envelopes')['status'])->toBe('created');
});

it('sends a document with a signature field at the marker, numbered when several sign', function () {
    fakeDocuSign(['/envelopes' => ['envelopeId' => ENVELOPE_ID, 'status' => 'sent']]);

    signedOutput('docusign_send_document', [
        'file_base64' => base64_encode("%PDF-1.4\nbody"),
        'mime_type' => 'application/pdf',
        'signers' => [['name' => 'Ada', 'email' => 'ada@example.com'], ['name' => 'Bob', 'email' => 'bob@example.com']],
        'subject' => 'Sign the agreement',
    ]);

    $request = sentTo('/envelopes');
    $signers = $request['recipients']['signers'];

    expect($request['documents'][0])->toMatchArray(['documentBase64' => base64_encode("%PDF-1.4\nbody"), 'fileExtension' => 'pdf', 'documentId' => '1'])
        ->and($signers[0]['tabs']['signHereTabs'][0]['anchorString'])->toBe('/sign1/')
        ->and($signers[1]['tabs']['signHereTabs'][0]['anchorString'])->toBe('/sign2/')
        ->and($signers[0]['tabs']['signHereTabs'][0]['anchorIgnoreIfNotPresent'])->toBeTrue()
        ->and($signers[1]['recipientId'])->toBe('2');
});

it('uses the marker as written for a single signer', function () {
    fakeDocuSign(['/envelopes' => ['envelopeId' => ENVELOPE_ID, 'status' => 'sent']]);

    signedOutput('docusign_send_document', ['file_base64' => base64_encode('%PDF-1.4'), 'mime_type' => 'application/pdf', 'signers' => [['name' => 'Ada', 'email' => 'ada@example.com']], 'subject' => 'Sign', 'signature_marker' => '\\s1\\']);

    expect(sentTo('/envelopes')['recipients']['signers'][0]['tabs']['signHereTabs'][0]['anchorString'])->toBe('\\s1\\');
});

it('refuses to send what it should not, before calling DocuSign', function () {
    Http::fake();
    $good = ['signers' => [['name' => 'A', 'email' => 'a@b.com']], 'subject' => 'S', 'file_base64' => base64_encode('%PDF-1.4'), 'mime_type' => 'application/pdf'];

    expect(fn () => executeNode('docusign_send_document', ['access_token' => 't', ...$good, 'file_base64' => base64_encode('PK'), 'mime_type' => 'application/zip']))->toThrow(InvalidArgumentException::class, 'PDF, Word')
        ->and(fn () => executeNode('docusign_send_document', ['access_token' => 't', ...$good, 'signers' => []]))->toThrow(InvalidArgumentException::class, 'at least one signer')
        ->and(fn () => executeNode('docusign_send_document', ['access_token' => 't', ...$good, 'signers' => [['name' => 'A', 'email' => 'nope']]]))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('docusign_send_document', ['access_token' => 't', ...$good, 'signers' => [['name' => '', 'email' => 'a@b.com']]]))->toThrow(InvalidArgumentException::class, 'needs a name')
        ->and(fn () => executeNode('docusign_send_document', ['access_token' => 't', ...$good, 'subject' => ' ']))->toThrow(InvalidArgumentException::class, 'subject')
        ->and(fn () => executeNode('docusign_send_document', ['access_token' => 't', ...$good, 'file_base64' => '', 'file_url' => 'http://127.0.0.1/x.pdf']))->toThrow(RuntimeException::class)
        ->and(fn () => executeNode('docusign_send_from_template', ['access_token' => 't', 'template_id' => 't', 'signers' => [['name' => 'A', 'email' => 'a@b.com']]]))->toThrow(InvalidArgumentException::class, 'needs a role')
        ->and(fn () => executeNode('docusign_send_from_template', ['access_token' => 't', 'template_id' => ' ', 'signers' => []]))->toThrow(InvalidArgumentException::class, 'template');

    Http::assertNothingSent();
});

it('uses the default account, a named one, and says when it cannot find one', function () {
    $accounts = [
        ['account_id' => 'acc-0', 'account_name' => 'Sandbox', 'is_default' => false, 'base_uri' => 'https://demo.docusign.net'],
        ['account_id' => 'acc-1', 'account_name' => 'Acme', 'is_default' => true, 'base_uri' => 'https://na3.docusign.net'],
    ];
    fakeDocuSign(['/envelopes/'.ENVELOPE_ID => ['envelopeId' => ENVELOPE_ID, 'status' => 'sent']], $accounts);
    Http::fake(['demo.docusign.net/*' => Http::response(['envelopeId' => ENVELOPE_ID, 'status' => 'sent'])]);

    signedOutput('docusign_get_envelope', ['envelope_id' => ENVELOPE_ID]);
    signedOutput('docusign_get_envelope', ['envelope_id' => ENVELOPE_ID, 'account' => 'sandbox']);

    expect(collect(Http::recorded())->contains(fn (array $pair): bool => str_contains($pair[0]->url(), 'na3.docusign.net')))->toBeTrue()
        ->and(collect(Http::recorded())->contains(fn (array $pair): bool => str_contains($pair[0]->url(), 'demo.docusign.net/restapi/v2.1/accounts/acc-0')))->toBeTrue()
        ->and(fn () => executeNode('docusign_get_envelope', ['access_token' => 't2', 'envelope_id' => ENVELOPE_ID, 'account' => 'Nope']))->toThrow(InvalidArgumentException::class, 'no DocuSign account called “Nope”');
});

it('refuses a login with no usable account, and an account server that is not https', function () {
    fakeDocuSign([], []);

    expect(fn () => executeNode('docusign_list_templates', ['access_token' => 'a']))->toThrow(RuntimeException::class, 'no account that can send');

    fakeDocuSign([], [['account_id' => 'x', 'account_name' => 'Evil', 'is_default' => true, 'base_uri' => 'http://169.254.169.254']]);

    expect(fn () => executeNode('docusign_list_templates', ['access_token' => 'b']))->toThrow(RuntimeException::class, 'no account that can send');
});

it('reports where a document stands, signer by signer', function () {
    fakeDocuSign(['/envelopes/'.ENVELOPE_ID => ['envelopeId' => ENVELOPE_ID, 'status' => 'completed', 'emailSubject' => 'Agreement', 'sentDateTime' => '2026-10-01T10:00:00Z', 'completedDateTime' => '2026-10-02T09:00:00Z', 'recipients' => ['signers' => [
        ['name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'completed', 'signedDateTime' => '2026-10-02T09:00:00Z', 'roleName' => 'Client'],
    ]]]]);

    $output = signedOutput('docusign_get_envelope', ['envelope_id' => ENVELOPE_ID]);

    expect($output)->toMatchArray(['envelope_id' => ENVELOPE_ID, 'status' => 'completed', 'result' => 'completed', 'is_complete' => true, 'subject' => 'Agreement', 'completed_at' => '2026-10-02T09:00:00Z'])
        ->and($output['signers'][0])->toMatchArray(['name' => 'Ada', 'status' => 'completed', 'signed_at' => '2026-10-02T09:00:00Z', 'role' => 'Client']);

    expect(sentTo('/envelopes/'.ENVELOPE_ID)['include'])->toBe('recipients');
});

it('refuses an envelope ID that is not one before it can reach an address', function () {
    Http::fake();

    foreach (['../../users', '123', ' ', ENVELOPE_ID.'/documents'] as $bad) {
        expect(fn () => executeNode('docusign_get_envelope', ['access_token' => 't', 'envelope_id' => $bad]))->toThrow(InvalidArgumentException::class, 'not an envelope ID');
    }

    Http::assertNothingSent();
});

it('lists documents by status and since a date', function () {
    fakeDocuSign(['/envelopes' => ['envelopes' => [['envelopeId' => ENVELOPE_ID, 'status' => 'completed', 'emailSubject' => 'A']]]]);

    $output = signedOutput('docusign_list_envelopes', ['status' => 'declined', 'since' => '2026-10-01T00:00:00Z', 'search' => 'contract', 'limit' => 10]);

    expect($output['count'])->toBe(1)->and($output['envelopes'][0]['envelope_id'])->toBe(ENVELOPE_ID);

    $request = sentTo('/envelopes');

    expect($request['status'])->toBe('declined')
        ->and($request['from_date'])->toBe('2026-10-01T00:00:00Z')
        ->and($request['search_text'])->toBe('contract')
        ->and($request['count'])->toEqual(10);
});

it('starts a flow for each document that is signed', function () {
    $node = app(NodeRegistry::class)->resolve('docusign_list_envelopes');

    expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
        ->and($node->triggerName())->toBe('DocuSign: Document signed')
        ->and($node->triggerItemId(['envelope_id' => 'abc']))->toBe('abc')
        ->and($node->triggerListsNewestFirst())->toBeTrue()
        ->and($node->triggerReadConfig(['status' => 'completed', 'since' => '2020-01-01'])['limit'])->toBe(100)
        ->and(array_map($node->triggerItemId(...), $node->triggerItems(['envelopes' => [['envelope_id' => 'a'], ['envelope_id' => 'b']]], [])))->toBe(['a', 'b']);
});

it('downloads the signed document as a PDF file, with the certificate when asked', function () {
    fakeDocuSign(['/envelopes/'.ENVELOPE_ID.'/documents/combined' => Http::response('%PDF-1.4 signed', 200, ['Content-Type' => 'application/pdf'])]);

    $output = signedOutput('docusign_download_document', ['envelope_id' => ENVELOPE_ID, 'filename' => 'agreement', 'include_certificate' => true]);

    expect($output['file'])->toMatchArray(['filename' => 'agreement.pdf', 'mime_type' => 'application/pdf', 'size' => 15])
        ->and(base64_decode($output['file']['content_base64']))->toBe('%PDF-1.4 signed')
        ->and(sentTo('/documents/combined')['certificate'])->toBe('true');
});

it('refuses a signed document too large to hold', function () {
    fakeDocuSign(['/envelopes/'.ENVELOPE_ID.'/documents/combined' => Http::response(str_repeat('a', 10 * 1024 * 1024 + 1), 200, ['Content-Type' => 'application/pdf'])]);

    expect(fn () => executeNode('docusign_download_document', ['access_token' => 't', 'envelope_id' => ENVELOPE_ID]))->toThrow(RuntimeException::class, 'too large');
});

it('cancels a document and tells the signers why', function () {
    fakeDocuSign(['/envelopes/'.ENVELOPE_ID => ['envelopeId' => ENVELOPE_ID]]);

    $output = signedOutput('docusign_void_envelope', ['envelope_id' => ENVELOPE_ID, 'reason' => 'Terms changed']);

    expect($output)->toMatchArray(['status' => 'voided', 'voided' => true])
        ->and(sentTo('/envelopes/'.ENVELOPE_ID)->method())->toBe('PUT')
        ->and(sentTo('/envelopes/'.ENVELOPE_ID)->data())->toMatchArray(['status' => 'voided', 'voidedReason' => 'Terms changed'])
        ->and(fn () => executeNode('docusign_void_envelope', ['access_token' => 't', 'envelope_id' => ENVELOPE_ID, 'reason' => ' ']))->toThrow(InvalidArgumentException::class, 'reason');
});

it('lists templates with the roles each expects', function () {
    fakeDocuSign(['/templates' => ['envelopeTemplates' => [['templateId' => 't1', 'name' => 'NDA', 'recipients' => ['signers' => [['roleName' => 'Client'], ['roleName' => 'Counsel']]]]]]]);

    expect(signedOutput('docusign_list_templates', ['search' => 'nda'])['templates'][0])->toMatchArray(['template_id' => 't1', 'name' => 'NDA', 'roles' => ['Client', 'Counsel']]);
});

it('explains a DocuSign failure in plain words', function (int $status, string $expected) {
    fakeDocuSign(['/templates' => Http::response(['errorCode' => 'X', 'message' => 'Detail here'], $status)]);

    expect(fn () => executeNode('docusign_list_templates', ['access_token' => 'e'.$status]))->toThrow(RuntimeException::class, $expected);
})->with([[401, 'DocuSign refused the API key'], [429, 'DocuSign is limiting requests'], [400, 'DocuSign rejected the request: Detail here']]);

/* -------------------------------------------------------------- Dropbox Sign */

it('signs in to Dropbox Sign with the key as the user name', function () {
    Http::fake(['api.hellosign.com/*' => Http::response(['signature_request' => ['signature_request_id' => SIGN_REQUEST_ID]])]);

    signedOutput('dropbox_sign_get_request', ['signature_request_id' => SIGN_REQUEST_ID, 'access_token' => 'my-key']);

    expect(sentTo('/signature_request/'.SIGN_REQUEST_ID)->header('Authorization'))->toBe(['Basic '.base64_encode('my-key:')]);
});

it('sends a document as a multipart form, signers numbered in order, in test mode when asked', function () {
    Http::fake(['api.hellosign.com/v3/signature_request/send' => Http::response(['signature_request' => ['signature_request_id' => SIGN_REQUEST_ID, 'title' => 'Agreement', 'test_mode' => true, 'signatures' => [['signer_name' => 'Ada', 'signer_email_address' => 'ada@example.com', 'status_code' => 'awaiting_signature']]]])]);

    $output = signedOutput('dropbox_sign_send_request', [
        'file_base64' => base64_encode("%PDF-1.4\nbody"),
        'mime_type' => 'application/pdf',
        'signers' => [['name' => 'Ada', 'email' => 'ada@example.com'], ['name' => 'Bob', 'email' => 'bob@example.com']],
        'title' => 'Agreement',
        'cc' => ['legal@example.com', 'not-an-email'],
        'test_mode' => true,
    ]);

    expect($output)->toMatchArray(['signature_request_id' => SIGN_REQUEST_ID, 'status' => 'awaiting_signature', 'test_mode' => true, 'sent_to' => ['ada@example.com', 'bob@example.com']]);

    $parts = collect(sentTo('/signature_request/send')->data())->mapWithKeys(fn (array $part): array => [$part['name'] => $part['contents']]);

    expect($parts['title'])->toBe('Agreement')
        ->and($parts['subject'])->toBe('Agreement')
        ->and($parts['signers[0][email_address]'])->toBe('ada@example.com')
        ->and($parts['signers[1][order]'])->toBe('1')
        ->and($parts['cc_email_addresses[0]'])->toBe('legal@example.com')
        ->and($parts['test_mode'])->toBe('1')
        ->and($parts)->not->toHaveKey('message')
        ->and($parts)->toHaveKey('files[0]')
        ->and($parts['files[0]'])->toBe("%PDF-1.4\nbody");
});

it('sends in real mode unless test mode is on', function () {
    Http::fake(['api.hellosign.com/*' => Http::response(['signature_request' => ['signature_request_id' => SIGN_REQUEST_ID]])]);

    signedOutput('dropbox_sign_send_request', ['file_base64' => base64_encode('%PDF-1.4'), 'mime_type' => 'application/pdf', 'signers' => [['name' => 'A', 'email' => 'a@b.com']], 'title' => 'T']);

    $parts = collect(sentTo('/signature_request/send')->data())->mapWithKeys(fn (array $part): array => [$part['name'] => $part['contents']]);

    expect($parts['test_mode'])->toBe('0');
});

it('sends a template, filling each role and its merge fields', function () {
    Http::fake(['api.hellosign.com/*' => Http::response(['signature_request' => ['signature_request_id' => SIGN_REQUEST_ID]])]);

    signedOutput('dropbox_sign_send_from_template', [
        'template_id' => TEMPLATE_ID,
        'signers' => [['role' => 'Client', 'name' => 'Ada', 'email' => 'ada@example.com']],
        'custom_fields' => ['company' => 'Initech'],
        'test_mode' => true,
    ]);

    $request = sentTo('/signature_request/send_with_template');

    expect($request['template_ids'])->toBe([TEMPLATE_ID])
        ->and($request['signers'][0])->toBe(['role' => 'Client', 'name' => 'Ada', 'email_address' => 'ada@example.com'])
        ->and($request['custom_fields'])->toBe([['name' => 'company', 'value' => 'Initech']])
        ->and($request['test_mode'])->toBe(1);
});

it('refuses to send what it should not, before calling Dropbox Sign', function () {
    Http::fake();
    $good = ['file_base64' => base64_encode('%PDF-1.4'), 'mime_type' => 'application/pdf', 'signers' => [['name' => 'A', 'email' => 'a@b.com']], 'title' => 'T', 'access_token' => 'k'];

    expect(fn () => executeNode('dropbox_sign_send_request', [...$good, 'mime_type' => 'application/zip']))->toThrow(InvalidArgumentException::class, 'PDF, Word')
        ->and(fn () => executeNode('dropbox_sign_send_request', [...$good, 'title' => ' ']))->toThrow(InvalidArgumentException::class, 'title')
        ->and(fn () => executeNode('dropbox_sign_send_request', [...$good, 'signers' => [['name' => 'A', 'email' => 'bad']]]))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('dropbox_sign_send_from_template', ['access_token' => 'k', 'template_id' => '../x', 'signers' => [['role' => 'C', 'name' => 'A', 'email' => 'a@b.com']]]))->toThrow(InvalidArgumentException::class, 'not a template ID')
        ->and(fn () => executeNode('dropbox_sign_send_from_template', ['access_token' => 'k', 'template_id' => TEMPLATE_ID, 'signers' => [['name' => 'A', 'email' => 'a@b.com']]]))->toThrow(InvalidArgumentException::class, 'needs a role');

    Http::assertNothingSent();
});

it('turns a signature request into a plain status', function (array $flags, string $status) {
    Http::fake(['api.hellosign.com/*' => Http::response(['signature_request' => ['signature_request_id' => SIGN_REQUEST_ID, 'created_at' => 1760000000, 'metadata' => ['order' => '7'], ...$flags, 'signatures' => [['signer_name' => 'Ada', 'signer_email_address' => 'ada@example.com', 'status_code' => 'signed', 'signed_at' => 1760003600]]]])]);

    $output = signedOutput('dropbox_sign_get_request', ['signature_request_id' => SIGN_REQUEST_ID]);

    expect($output)->toMatchArray(['status' => $status, 'result' => $status, 'is_complete' => $status === 'complete', 'created_at' => '2025-10-09T08:53:20+00:00'])
        ->and($output['signers'][0])->toMatchArray(['name' => 'Ada', 'status' => 'signed', 'signed_at' => '2025-10-09T09:53:20+00:00']);
})->with([
    'complete' => [['is_complete' => true], 'complete'],
    'declined' => [['is_declined' => true], 'declined'],
    'error' => [['has_error' => true], 'error'],
    'waiting' => [[], 'awaiting_signature'],
    'complete wins over error' => [['is_complete' => true, 'has_error' => true], 'complete'],
]);

it('lists signature requests by whether they are signed', function (string $which, string $expectedQuery) {
    Http::fake(['api.hellosign.com/*' => Http::response(['signature_requests' => [['signature_request_id' => SIGN_REQUEST_ID, 'is_complete' => true]]])]);

    $output = signedOutput('dropbox_sign_list_requests', ['which' => $which, 'search' => 'subject:contract', 'limit' => 5]);

    expect($output['count'])->toBe(1);

    $request = sentTo('/signature_request/list');

    expect($request['page_size'])->toEqual(5)
        ->and($request['query'] ?? '')->toBe($expectedQuery);
})->with([
    'signed' => ['complete', 'complete:true subject:contract'],
    'pending' => ['pending', 'complete:false subject:contract'],
    'all' => ['all', 'subject:contract'],
]);

it('starts a flow for each Dropbox Sign document that is signed', function () {
    $node = app(NodeRegistry::class)->resolve('dropbox_sign_list_requests');

    expect($node)->toBeInstanceOf(SupportsTriggerMode::class)
        ->and($node->triggerName())->toBe('Dropbox Sign: Document signed')
        ->and($node->triggerItemId(['signature_request_id' => 'abc']))->toBe('abc')
        ->and($node->triggerReadConfig([])['limit'])->toBe(100);
});

it('downloads the signed Dropbox Sign document as a PDF file', function () {
    Http::fake(['api.hellosign.com/v3/signature_request/files/*' => Http::response('%PDF-1.4 signed', 200, ['Content-Type' => 'application/pdf'])]);

    $output = signedOutput('dropbox_sign_download_files', ['signature_request_id' => SIGN_REQUEST_ID, 'filename' => 'contract.PDF']);

    expect($output['file'])->toMatchArray(['filename' => 'contract.PDF', 'mime_type' => 'application/pdf'])
        ->and(base64_decode($output['file']['content_base64']))->toBe('%PDF-1.4 signed')
        ->and(sentTo('/signature_request/files/')['file_type'])->toBe('pdf');
});

it('cancels a request and reminds a signer', function () {
    Http::fake(['api.hellosign.com/*' => Http::response(['signature_request' => ['signature_request_id' => SIGN_REQUEST_ID]])]);

    expect(signedOutput('dropbox_sign_cancel_request', ['signature_request_id' => SIGN_REQUEST_ID]))->toMatchArray(['cancelled' => true])
        ->and(signedOutput('dropbox_sign_remind', ['signature_request_id' => SIGN_REQUEST_ID, 'email' => 'Ada@Example.com']))->toMatchArray(['reminded' => 'ada@example.com'])
        ->and(sentTo('/signature_request/remind/')->data())->toMatchArray(['email_address' => 'ada@example.com'])
        ->and(fn () => executeNode('dropbox_sign_remind', ['access_token' => 'k', 'signature_request_id' => SIGN_REQUEST_ID, 'email' => 'nope']))->toThrow(InvalidArgumentException::class, 'not an email')
        ->and(fn () => executeNode('dropbox_sign_cancel_request', ['access_token' => 'k', 'signature_request_id' => '../../account']))->toThrow(InvalidArgumentException::class, 'not a signature request ID');
});

it('lists Dropbox Sign templates with the roles each expects', function () {
    Http::fake(['api.hellosign.com/*' => Http::response(['templates' => [['template_id' => TEMPLATE_ID, 'title' => 'NDA', 'signer_roles' => [['name' => 'Client'], ['name' => 'Counsel']], 'is_creator' => true]]])]);

    expect(signedOutput('dropbox_sign_list_templates', ['search' => 'nda'])['templates'][0])->toMatchArray(['template_id' => TEMPLATE_ID, 'title' => 'NDA', 'roles' => ['Client', 'Counsel'], 'is_creator' => true])
        ->and(sentTo('/template/list')['query'])->toBe('title:nda');
});

it('explains a Dropbox Sign failure in plain words', function (int $status, string $expected) {
    Http::fake(['api.hellosign.com/*' => Http::response(['error' => ['error_msg' => 'Free accounts must use test mode', 'error_name' => 'x']], $status)]);

    expect(fn () => executeNode('dropbox_sign_get_request', ['access_token' => 'k', 'signature_request_id' => SIGN_REQUEST_ID]))->toThrow(RuntimeException::class, $expected);
})->with([[401, 'Dropbox Sign refused the API key'], [402, 'no credit left'], [429, 'Dropbox Sign is limiting requests'], [404, 'found nothing']]);

/* ------------------------------------------------------------------- shared */

it('registers the e-signature nodes under their own app categories', function () {
    $expected = [
        'docusign' => ['docusign_send_from_template', 'docusign_send_document', 'docusign_get_envelope', 'docusign_list_envelopes', 'docusign_download_document', 'docusign_void_envelope', 'docusign_list_templates'],
        'dropbox_sign' => ['dropbox_sign_send_request', 'dropbox_sign_send_from_template', 'dropbox_sign_get_request', 'dropbox_sign_list_requests', 'dropbox_sign_download_files', 'dropbox_sign_cancel_request', 'dropbox_sign_remind', 'dropbox_sign_list_templates'],
    ];

    foreach ($expected as $category => $types) {
        foreach ($types as $type) {
            $node = app(NodeRegistry::class)->resolve($type);

            expect($node->category())->toBe($category)
                ->and($node->configSchema()['properties'])->toHaveKey('credential_id')
                ->and($node->outputSchema()['properties'])->not->toBeEmpty();
        }
    }
});

it('marks sending as reaching other people and cancelling as destructive', function () {
    $registry = app(NodeRegistry::class);

    foreach (['docusign_send_from_template', 'docusign_send_document', 'dropbox_sign_send_request', 'dropbox_sign_send_from_template', 'dropbox_sign_remind'] as $type) {
        expect($registry->resolve($type)->effect([])->value)->toBe('external');
    }

    foreach (['docusign_void_envelope', 'dropbox_sign_cancel_request'] as $type) {
        expect($registry->resolve($type)->effect([])->value)->toBe('destructive');
    }

    foreach (['docusign_get_envelope', 'docusign_list_envelopes', 'docusign_download_document', 'dropbox_sign_get_request', 'dropbox_sign_download_files'] as $type) {
        expect($registry->resolve($type)->effect([])->isRead())->toBeTrue();
    }
});
