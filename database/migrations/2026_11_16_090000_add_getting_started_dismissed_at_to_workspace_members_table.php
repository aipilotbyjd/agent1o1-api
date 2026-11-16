<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->timestamp('getting_started_dismissed_at')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropColumn('getting_started_dismissed_at');
        });
    }
};
