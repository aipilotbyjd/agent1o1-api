<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('event_data');
            $table->string('provider_event')->nullable()->after('provider');
            $table->string('dedup_key')->nullable()->after('provider_event');

            $table->index(['trigger_id', 'dedup_key']);
        });
    }

    public function down(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->dropIndex(['trigger_id', 'dedup_key']);
            $table->dropColumn(['provider', 'provider_event', 'dedup_key']);
        });
    }
};
