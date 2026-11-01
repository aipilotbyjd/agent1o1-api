<?php

use App\Services\Assistant\Branding\BrandRepository;

function brand()
{
    return app(BrandRepository::class)->current();
}

it('resolves the brand from config', function () {
    config(['assistant.brand.name' => 'Nimbus', 'assistant.brand.tagline' => 'Always on']);

    expect(brand())
        ->name->toBe('Nimbus')
        ->tagline->toBe('Always on');
});

it('fills the brand name into feature names', function () {
    config(['assistant.brand.name' => 'Nimbus']);

    expect(brand()->feature('daily'))->toBe('Nimbus Daily')
        ->and(brand()->feature('inbox'))->toBe('Nimbus Inbox')
        ->and(brand()->feature('situations'))->toBe('Situations');
});

it('headlines an unknown feature key instead of leaking it raw', function () {
    expect(brand()->feature('weekly_digest'))->toBe('Weekly Digest');
});

it('builds the email address from the local part and inbound domain', function () {
    config(['assistant.brand.email_local_part' => 'nimbus', 'assistant.brand.inbound_domain' => 'example.test']);

    expect(brand()->email())->toBe('nimbus@example.test');
});

it('accepts mail to the current address and former aliases only', function (string $address, bool $accepted) {
    config([
        'assistant.brand.email_local_part' => 'nimbus',
        'assistant.brand.email_aliases' => ['orb'],
        'assistant.brand.inbound_domain' => 'example.test',
    ]);

    expect(brand()->acceptsEmailTo($address))->toBe($accepted);
})->with([
    'current' => ['nimbus@example.test', true],
    'current, mixed case' => [' Nimbus@Example.TEST ', true],
    'alias' => ['orb@example.test', true],
    'unknown local part' => ['someone@example.test', false],
    'other domain' => ['nimbus@elsewhere.test', false],
]);

it('serialises absolute asset urls and resolved feature names', function () {
    config([
        'assistant.brand.name' => 'Nimbus',
        'assistant.brand.icon_url' => '/brand/assistant/icon.svg',
        'assistant.brand.avatar_url' => 'https://cdn.example.test/avatar.png',
    ]);

    $payload = brand()->toArray();

    expect($payload['icon_url'])->toBe(url('/brand/assistant/icon.svg'))
        ->and($payload['avatar_url'])->toBe('https://cdn.example.test/avatar.png')
        ->and($payload['features']['daily'])->toBe('Nimbus Daily');
});

it('fills the brand name into the sms signature', function () {
    config(['assistant.brand.name' => 'Nimbus']);

    expect(brand()->smsSignature())->toBe('- Nimbus');
});
