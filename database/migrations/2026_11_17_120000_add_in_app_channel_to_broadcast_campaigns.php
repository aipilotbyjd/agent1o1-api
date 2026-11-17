<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a campaign reach the notification bell as well as the inbox. Existing
 * campaigns stay email-only. The bell shows a short summary rather than the
 * Markdown email body, so an in-app-only campaign has no body at all. Each
 * recipient row records the notification it created, which is what makes a
 * retry safe and a recall possible.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->string('channel')->default('email')->after('category');
            $table->text('body_markdown')->nullable()->change();
            $table->string('notification_summary', 500)->nullable()->after('body_markdown');
            $table->string('action_label', 40)->nullable()->after('notification_summary');
            $table->string('action_url', 2048)->nullable()->after('action_label');
            $table->timestamp('recalled_at')->nullable()->after('finished_at');
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->uuid('notification_id')->nullable()->after('email');
            $table->timestamp('notified_at')->nullable()->after('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->dropColumn(['notification_id', 'notified_at']);
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropColumn(['channel', 'notification_summary', 'action_label', 'action_url', 'recalled_at']);
        });
    }
};
