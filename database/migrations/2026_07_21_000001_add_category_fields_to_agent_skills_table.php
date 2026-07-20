<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_skills', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
            $table->string('icon')->nullable()->after('category');
            $table->string('color')->nullable()->after('icon');
            $table->json('tags')->nullable()->after('color');

            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_skills', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'category']);
            $table->dropColumn(['category', 'icon', 'color', 'tags']);
        });
    }
};
