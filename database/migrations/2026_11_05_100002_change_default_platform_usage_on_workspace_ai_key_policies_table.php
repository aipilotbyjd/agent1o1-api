<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring-your-own-key first: once a workspace has a key of its own, the
 * platform's keys are used only for models none of its keys cover
 * (`when_no_key`), instead of also backing up its keys (`fallback`).
 * Policies already saved keep the value their admin chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_ai_key_policies', function (Blueprint $table) {
            $table->string('platform_usage')->default('when_no_key')->change();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_ai_key_policies', function (Blueprint $table) {
            $table->string('platform_usage')->default('fallback')->change();
        });
    }
};
