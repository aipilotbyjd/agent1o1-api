<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The super admin's switches for each bring-your-own-key provider: whether
 * customers can use their own key for it, whether one-click connect is
 * offered, and how it is shown. `config/byok.php` still defines what each
 * provider is; a provider with no row here keeps its config defaults. See
 * `App\Services\Ai\AiProviderSettings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->unique();
            $table->boolean('byok_enabled')->default(true);
            $table->boolean('connect_enabled')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->string('free_tier_note', 200)->nullable();
            $table->string('disabled_message', 300)->nullable();
            $table->text('connect_client_id')->nullable();
            $table->text('connect_client_secret')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};
