<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsCreateContactNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsDeleteContactNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsGetContactNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsListContactsNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsListUpdatedContactsNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsSearchContactsNode;
use App\Nodes\Integrations\GoogleContacts\GoogleContactsUpdateContactNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

const CONTACTS_BASE = ['access_token' => 'ya29-test'];

const ADA_PERSON = [
    'resourceName' => 'people/c1',
    'etag' => 'e1',
    'names' => [['displayName' => 'Ada Lovelace', 'givenName' => 'Ada', 'familyName' => 'Lovelace']],
    'emailAddresses' => [['value' => 'ada@example.com'], ['value' => 'ada@work.example']],
    'phoneNumbers' => [['value' => '+44 20']],
    'organizations' => [['name' => 'Analytical Engines', 'title' => 'Programmer']],
    'biographies' => [['value' => 'First programmer']],
];

const ADA_CONTACT = [
    'resource_name' => 'people/c1',
    'etag' => 'e1',
    'name' => 'Ada Lovelace',
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
    'emails' => ['ada@example.com', 'ada@work.example'],
    'phone' => '+44 20',
    'phones' => ['+44 20'],
    'company' => 'Analytical Engines',
    'job_title' => 'Programmer',
    'notes' => 'First programmer',
];

it('registers every google contacts node under the google_contacts category', function () {
    foreach (['list_contacts', 'search_contacts', 'get_contact', 'create_contact', 'update_contact', 'delete_contact'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_contacts_{$suffix}")->category())->toBe('google_contacts');
    }
});

it('lists contacts in a flat shape, most recently changed first', function () {
    Http::fake(['people.googleapis.com/*' => Http::response(['connections' => [ADA_PERSON], 'totalPeople' => 1])]);

    $output = (new GoogleContactsListContactsNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'max_results' => 5], []);

    expect($output)->toBe(['contacts' => [ADA_CONTACT], 'total' => 1]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/people/me/connections')
        && str_contains($request->url(), 'sortOrder=LAST_MODIFIED_DESCENDING')
        && str_contains($request->url(), 'pageSize=5'));
});

it('works as a new contact trigger keyed by resource name', function () {
    $node = new GoogleContactsListContactsNode;

    expect($node->triggerItems(['contacts' => [ADA_CONTACT]], []))->toHaveCount(1)
        ->and($node->triggerItemId(ADA_CONTACT))->toBe('people/c1')
        ->and($node->triggerListsNewestFirst())->toBeTrue();
});

it('searches contacts', function () {
    Http::fake(['people.googleapis.com/*' => Http::response(['results' => [['person' => ADA_PERSON]]])]);

    $output = (new GoogleContactsSearchContactsNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'query' => 'ada'], []);

    expect($output['contacts'][0]['email'])->toBe('ada@example.com');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/people:searchContacts') && str_contains($request->url(), 'query=ada'));
});

it('returns an empty list when a search finds nobody', function () {
    Http::fake(['people.googleapis.com/*' => Http::response([])]);

    expect((new GoogleContactsSearchContactsNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'query' => 'nobody'], []))->toBe(['contacts' => []]);
});

it('reads one contact', function () {
    Http::fake(['people.googleapis.com/*' => Http::response(ADA_PERSON)]);

    $output = (new GoogleContactsGetContactNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'resource_name' => 'people/c1'], []);

    expect($output)->toBe(ADA_CONTACT);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/people/c1?personFields='));
});

it('refuses a contact id that is not a person', function (string $id) {
    Http::fake();

    (new GoogleContactsGetContactNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'resource_name' => $id], []);
})->with(['c1', 'people/', 'people/c1/../../x', 'otherContacts/c1', 'people/c1?x=1'])->throws(InvalidArgumentException::class, 'must look like people/');

it('creates a contact from the filled-in fields only', function () {
    Http::fake(['people.googleapis.com/*' => Http::response(ADA_PERSON)]);

    (new GoogleContactsCreateContactNode)->execute(Run::factory()->create(), [
        ...CONTACTS_BASE, 'first_name' => 'Ada', 'last_name' => '', 'email' => 'ada@example.com', 'company' => 'Analytical Engines', 'notes' => 'First programmer',
    ], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/people:createContact?personFields=')
        && $request->data() === [
            'names' => [['givenName' => 'Ada']],
            'emailAddresses' => [['value' => 'ada@example.com']],
            'organizations' => [['name' => 'Analytical Engines']],
            'biographies' => [['value' => 'First programmer', 'contentType' => 'TEXT_PLAIN']],
        ]);
});

it('refuses to create an empty contact', function () {
    Http::fake();

    (new GoogleContactsCreateContactNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'first_name' => ' '], []);
})->throws(InvalidArgumentException::class, 'at least a name');

it('updates only the changed fields with the current etag', function () {
    Http::fake([
        'people.googleapis.com/v1/people/c1?personFields=metadata' => Http::response(['resourceName' => 'people/c1', 'etag' => 'fresh']),
        'people.googleapis.com/*' => Http::response(ADA_PERSON),
    ]);

    (new GoogleContactsUpdateContactNode)->execute(Run::factory()->create(), [
        ...CONTACTS_BASE, 'resource_name' => 'people/c1', 'phone' => '+1 555', 'job_title' => 'Founder',
    ], []);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/people/c1:updateContact?updatePersonFields=phoneNumbers%2Corganizations')
        && $request['etag'] === 'fresh'
        && $request['phoneNumbers'] === [['value' => '+1 555']]
        && $request['organizations'] === [['title' => 'Founder']]);
});

it('refuses an update that changes nothing', function () {
    Http::fake();

    (new GoogleContactsUpdateContactNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'resource_name' => 'people/c1'], []);
})->throws(InvalidArgumentException::class, 'at least one field');

it('deletes a contact', function () {
    Http::fake(['people.googleapis.com/*' => Http::response([])]);

    $output = (new GoogleContactsDeleteContactNode)->execute(Run::factory()->create(), [...CONTACTS_BASE, 'resource_name' => 'people/c1'], []);

    expect($output)->toBe(['deleted' => true, 'resource_name' => 'people/c1']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/v1/people/c1:deleteContact'));
});

it('triggers when a contact is added or edited, because each edit gives it a new etag', function () {
    Http::fake(['people.googleapis.com/*' => Http::response(['connections' => [ADA_PERSON]])]);
    $node = new GoogleContactsListUpdatedContactsNode;

    $output = $node->execute(Run::factory()->create(), [...CONTACTS_BASE, 'max_results' => 5], []);

    expect($node->triggerItems($output, []))->toHaveCount(1)
        ->and($node->triggerItemId($output['contacts'][0]))->toBe('people/c1:e1')
        ->and($node->triggerItemId([...$output['contacts'][0], 'etag' => 'e2']))->toBe('people/c1:e2')
        ->and($node->triggerItemId(['resource_name' => 'people/c1']))->toBe('')
        ->and($node->triggerListsNewestFirst())->toBeTrue()
        ->and($node->triggerName())->toBe('Google Contacts: Contact added or updated');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'sortOrder=LAST_MODIFIED_DESCENDING') && str_contains($request->url(), 'pageSize=5'));
});
