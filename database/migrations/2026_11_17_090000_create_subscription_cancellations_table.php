<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_cancellations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('interval', 20)->nullable();
            $table->string('reason', 40)->nullable();
            $table->text('comment')->nullable();
            $table->string('offer', 20)->nullable();
            $table->string('outcome', 30);
            $table->unsignedSmallInteger('discount_percent')->nullable();
            $table->unsignedSmallInteger('discount_months')->nullable();
            $table->unsignedSmallInteger('months_subscribed')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'outcome', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_cancellations');
    }
};
