<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_types', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('icon');
            $table->string('docs_url')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('credential_types', function (Blueprint $table) {
            $table->dropColumn(['color', 'docs_url']);
        });
    }
};
