<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No foreign keys — survives execution pruning.
        Schema::create('archived_execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('execution_id')->index();
            $table->uuid('workspace_id')->index();
            $table->string('node_id')->nullable();
            $table->string('level', 20)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('logged_at');
            $table->timestamp('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_execution_logs');
    }
};
