<?php

namespace App\Nodes\Integrations\GoogleContacts;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;
use App\Nodes\Support\Field;
use InvalidArgumentException;

/**
 * Shared request handling and contact shaping for the Google Contacts node
 * family (the People API).
 */
abstract class AbstractGoogleContactsNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    /**
     * The person fields every node reads and writes.
     */
    protected const string PERSON_FIELDS = 'names,emailAddresses,phoneNumbers,organizations,biographies';

    public function category(): string
    {
        return 'google_contacts';
    }

    protected function baseUrl(): string
    {
        return 'https://people.googleapis.com/v1';
    }

    protected function appName(): string
    {
        return 'Google Contacts';
    }

    /**
     * The field a node takes a contact's resource name in.
     *
     * @return array<string, mixed>
     */
    protected function resourceNameField(): array
    {
        return Field::text('Contact ID', 'The "resource_name" of a contact, such as people/c1234567890.', 'people/c1234567890');
    }

    /**
     * A contact's resource name as a URL path, checked so it can only point
     * at a person.
     *
     * @throws InvalidArgumentException
     */
    protected function personPath(mixed $resourceName): string
    {
        $resourceName = trim((string) $resourceName);

        if (preg_match('#^people/[A-Za-z0-9_-]+$#', $resourceName) !== 1) {
            throw new InvalidArgumentException('Google Contacts: the contact ID must look like people/c1234567890.');
        }

        return '/'.$resourceName;
    }

    /**
     * A person in the flat shape every node returns.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    protected function contact(array $person): array
    {
        $emails = array_values(array_filter(array_map(fn (array $email): string => (string) ($email['value'] ?? ''), (array) ($person['emailAddresses'] ?? []))));
        $phones = array_values(array_filter(array_map(fn (array $phone): string => (string) ($phone['value'] ?? ''), (array) ($person['phoneNumbers'] ?? []))));
        $name = (array) ($person['names'][0] ?? []);
        $organization = (array) ($person['organizations'][0] ?? []);

        return [
            'resource_name' => $person['resourceName'] ?? null,
            'etag' => $person['etag'] ?? null,
            'name' => $name['displayName'] ?? null,
            'first_name' => $name['givenName'] ?? null,
            'last_name' => $name['familyName'] ?? null,
            'email' => $emails[0] ?? null,
            'emails' => $emails,
            'phone' => $phones[0] ?? null,
            'phones' => $phones,
            'company' => $organization['name'] ?? null,
            'job_title' => $organization['title'] ?? null,
            'notes' => $person['biographies'][0]['value'] ?? null,
        ];
    }

    /**
     * The contact fields a user filled in, as a People API person. A field
     * left empty is left out.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function personBody(array $config): array
    {
        $body = [];
        $first = trim((string) ($config['first_name'] ?? ''));
        $last = trim((string) ($config['last_name'] ?? ''));

        if ($first !== '' || $last !== '') {
            $body['names'] = [array_filter(['givenName' => $first, 'familyName' => $last])];
        }

        foreach (['emailAddresses' => 'email', 'phoneNumbers' => 'phone'] as $field => $key) {
            $value = trim((string) ($config[$key] ?? ''));

            if ($value !== '') {
                $body[$field] = [['value' => $value]];
            }
        }

        $company = trim((string) ($config['company'] ?? ''));
        $title = trim((string) ($config['job_title'] ?? ''));

        if ($company !== '' || $title !== '') {
            $body['organizations'] = [array_filter(['name' => $company, 'title' => $title])];
        }

        $notes = trim((string) ($config['notes'] ?? ''));

        if ($notes !== '') {
            $body['biographies'] = [['value' => $notes, 'contentType' => 'TEXT_PLAIN']];
        }

        return $body;
    }

    /**
     * The input fields shared by Create and Update.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function personFields(): array
    {
        return [
            'first_name' => Field::text('First name', null, 'Ada'),
            'last_name' => Field::text('Last name', null, 'Lovelace'),
            'email' => Field::text('Email', null, 'ada@example.com'),
            'phone' => Field::text('Phone', null, '+44 20 7946 0000'),
            'company' => Field::text('Company'),
            'job_title' => Field::text('Job title'),
            'notes' => Field::textarea('Notes'),
        ];
    }
}
