<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->foreignId('trigger_type_id')->nullable()->after('credential_id')
                ->constrained('trigger_types')->nullOnDelete();
            $table->string('webhook_external_id')->nullable()->after('webhook_provider');
            $table->string('webhook_status_message')->nullable()->after('webhook_status');

            $table->index('trigger_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trigger_type_id');
            $table->dropColumn(['webhook_external_id', 'webhook_status_message']);
        });
    }
};
