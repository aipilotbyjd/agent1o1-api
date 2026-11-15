<?php

use App\Services\SuperAdmin\Operations\TaskCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Puts the built-in checklist in place, so a new install starts with it.
     * Later additions come from `php artisan operations:sync-tasks`.
     */
    public function up(): void
    {
        app(TaskCatalog::class)->sync();
    }

    public function down(): void
    {
        // The tables are dropped by their own migrations.
    }
};
