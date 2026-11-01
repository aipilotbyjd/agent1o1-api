<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Super Admin System Health screen remembers: a snapshot of every
 * health check every few minutes (for uptime and incident history), and the
 * last runs of each scheduled command (so a command that silently stopped
 * running can be told apart from one that has simply not been due yet).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_health_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status', 16);
            $table->json('checks');
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('scheduled_command_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('command');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error', 500)->nullable();

            $table->index(['command', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_command_runs');
        Schema::dropIfExists('system_health_snapshots');
    }
};
