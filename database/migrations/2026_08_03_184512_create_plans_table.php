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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_monthly')->default(0);
            $table->unsignedInteger('price_yearly')->default(0);
            $table->unsignedInteger('credits_monthly')->default(0);
            $table->json('limits');
            $table->json('features');
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id_monthly')->nullable();
            $table->string('stripe_price_id_yearly')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('price_quarterly')->default(0);
            $table->unsignedInteger('price_lifetime')->default(0);
            $table->string('stripe_price_id_quarterly')->nullable();
            $table->string('stripe_price_id_lifetime')->nullable();
            $table->json('enabled_intervals')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('badge', 40)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
