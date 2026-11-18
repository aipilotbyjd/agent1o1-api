<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which lifecycle emails (`LifecycleEmail`) each user has been sent. The
 * unique pair is what keeps an email to once per person, however often the
 * sending job runs or overlaps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_email_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('sent_at');

            $table->unique(['user_id', 'email']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_email_sends');
    }
};
