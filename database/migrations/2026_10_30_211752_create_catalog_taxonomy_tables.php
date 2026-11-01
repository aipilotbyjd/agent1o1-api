<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an app is used for and who makes it, as rows an admin can add to,
     * rename and reorder, so a new function or vendor needs no deploy.
     */
    public function up(): void
    {
        Schema::create('catalog_functions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('catalog_vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        $functions = [
            'email' => 'Email',
            'messaging' => 'Messaging',
            'marketing' => 'Marketing',
            'analytics' => 'Analytics',
            'productivity' => 'Productivity',
            'storage' => 'Storage',
            'forms' => 'Forms & Surveys',
            'media' => 'Media',
            'crm-sales' => 'CRM & Sales',
            'support' => 'Support',
            'finance-payments' => 'Finance & Payments',
            'project-management' => 'Project management',
            'developer' => 'Developer',
            'commerce' => 'Commerce',
            'cloud-hosting' => 'Cloud & Hosting',
            'cms-website' => 'CMS & Website',
        ];

        $order = 0;

        foreach ($functions as $slug => $label) {
            DB::table('catalog_functions')->insert(['id' => (string) str()->uuid(), 'slug' => $slug, 'label' => $label, 'sort_order' => ++$order, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Where an app with no function is shown, so a new integration never goes missing.
        DB::table('catalog_functions')->insert(['id' => (string) str()->uuid(), 'slug' => 'other', 'label' => 'Other apps', 'sort_order' => 1000, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);

        $order = 0;

        foreach (['google' => 'Google', 'zoho' => 'Zoho', 'microsoft' => 'Microsoft', 'atlassian' => 'Atlassian'] as $slug => $label) {
            DB::table('catalog_vendors')->insert(['id' => (string) str()->uuid(), 'slug' => $slug, 'label' => $label, 'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_vendors');
        Schema::dropIfExists('catalog_functions');
    }
};
