<?php

namespace App\Services\SuperAdmin\System\Checks;

use Illuminate\Support\Facades\DB;

class DatabaseCheck implements HealthCheck
{
    public function name(): string
    {
        return 'database';
    }

    public function run(): array
    {
        DB::selectOne('select 1 as ok');

        return [
            'status' => self::HEALTHY,
            'message' => 'The database answered.',
            'details' => ['driver' => DB::connection()->getDriverName()],
        ];
    }
}
