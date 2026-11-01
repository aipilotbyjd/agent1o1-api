<?php

it('serves the brand without authentication', function () {
    config(['assistant.brand.name' => 'Nimbus', 'assistant.brand.email_local_part' => 'nimbus']);

    $this->getJson('/api/v1/app-config')
        ->assertSuccessful()
        ->assertJsonPath('data.brand.name', 'Nimbus')
        ->assertJsonPath('data.brand.features.daily', 'Nimbus Daily')
        ->assertJsonPath('data.brand.email', 'nimbus@'.config('assistant.brand.inbound_domain'))
        ->assertHeader('Cache-Control', 'max-age=300, public');
});
