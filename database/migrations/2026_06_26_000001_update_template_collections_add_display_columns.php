<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_collections', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('slug');
            $table->string('color')->nullable()->after('icon');
            $table->json('items')->nullable()->after('color');
            $table->boolean('is_featured')->default(false)->after('items');
            $table->dropColumn('template_ids');
        });
    }

    public function down(): void
    {
        Schema::table('template_collections', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color', 'items', 'is_featured']);
            $table->json('template_ids')->nullable();
        });
    }
};
