<?php

use App\Events\Assistant\BrandChanged;
use Illuminate\Support\Facades\Event;

it('shows the resolved brand', function () {
    config(['assistant.brand.name' => 'Nimbus', 'assistant.brand.email_local_part' => 'nimbus']);

    $this->artisan('assistant:brand-show')
        ->expectsOutputToContain('Nimbus Daily')
        ->expectsOutputToContain('nimbus@')
        ->assertSuccessful();
});

it('broadcasts the current brand on refresh', function () {
    Event::fake([BrandChanged::class]);
    config(['assistant.brand.name' => 'Nimbus']);

    $this->artisan('assistant:brand-refresh')->assertSuccessful();

    Event::assertDispatched(BrandChanged::class, fn (BrandChanged $event): bool => $event->brand->name === 'Nimbus'
        && $event->broadcastWith()['brand']['name'] === 'Nimbus');
});
