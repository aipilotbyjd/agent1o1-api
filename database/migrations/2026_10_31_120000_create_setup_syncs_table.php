<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per setup dataset: what was last applied, so the next sync can
     * tell whether anything changed.
     */
    public function up(): void
    {
        Schema::create('setup_syncs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dataset')->unique();
            $table->string('checksum', 40);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 20);
            $table->unsignedInteger('rows_before')->default(0);
            $table->unsignedInteger('rows_after')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_syncs');
    }
};
