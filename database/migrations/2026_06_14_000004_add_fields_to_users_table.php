<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->string('avatar')->nullable();
            $table->string('job_role')->nullable();
            $table->string('discovery_source')->nullable();
            $table->timestamp('onboarding_dismissed_at')->nullable();
            $table->foreignUuid('current_workspace_id')->nullable()->nullOnDelete()->constrained('workspaces');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_workspace_id']);
            $table->dropColumn(['is_admin', 'avatar', 'job_role', 'discovery_source', 'onboarding_dismissed_at', 'current_workspace_id']);
        });
    }
};
