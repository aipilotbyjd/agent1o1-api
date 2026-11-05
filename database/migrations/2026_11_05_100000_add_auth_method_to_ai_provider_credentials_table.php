<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-click connect: a credential can now be an OAuth access token that
 * expires (`token_expires_at`) and is refreshed, not only a pasted key.
 * See `App\Enums\Ai\AiProviderAuthMethod`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->string('auth_method')->default('api_key')->after('data');
            $table->timestamp('token_expires_at')->nullable()->after('auth_method');
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_credentials', function (Blueprint $table) {
            $table->dropColumn(['auth_method', 'token_expires_at']);
        });
    }
};
