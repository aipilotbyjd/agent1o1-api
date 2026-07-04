<?php

use Database\Seeders\PlanSeeder;

test('public plans endpoint returns active plans', function () {
    $this->seed(PlanSeeder::class);

    $this->getJson('/api/v1/plans')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);
});
