<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin alerting. A rule is a tunable signal (built-in rules are created
 * from `AlertRuleCatalog`); an event is one occurrence of it. The same rule
 * and subject share a `fingerprint`, so a condition that stays true updates
 * one open event instead of creating a new one every minute.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('type', 64);
            $table->json('config');
            $table->string('severity', 16)->default('warning');
            $table->unsignedSmallInteger('cooldown_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamp('snoozed_until')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('alert_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->string('fingerprint');
            $table->string('severity', 16);
            $table->string('status', 16)->default('open');
            $table->string('title', 500);
            $table->json('context')->nullable();
            $table->timestamp('first_fired_at');
            $table->timestamp('last_fired_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->uuid('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['alert_rule_id', 'fingerprint', 'status']);
            $table->index(['status', 'severity']);
            $table->index('first_fired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rules');
    }
};
