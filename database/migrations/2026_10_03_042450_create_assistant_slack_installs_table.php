<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assistant_slack_installs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slack_team_id')->unique();
            $table->string('team_name')->nullable();
            $table->text('bot_token');
            $table->string('bot_user_id')->nullable();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_slack_installs');
    }
};
