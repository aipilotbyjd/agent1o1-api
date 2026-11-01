<?php

namespace Tests;

use App\Services\Setup\SetupSyncer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Load the named setup datasets (and what they depend on), e.g.
     * `$this->syncSetup('connectors')`. Always re-applies.
     */
    public function syncSetup(string ...$datasets): void
    {
        app(SetupSyncer::class)->sync(only: array_values($datasets), force: true);
    }
}
