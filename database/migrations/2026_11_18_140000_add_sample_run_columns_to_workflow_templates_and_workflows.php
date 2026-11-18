<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sample runs (`SampleRuns`): a template carries example answers for its run
 * form, and a workflow remembers the template it was made from, so the
 * answers can be offered when it is first run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->json('sample_input')->nullable()->after('graph');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignUuid('template_id')->nullable()->after('folder_id')->constrained('workflow_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });

        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->dropColumn('sample_input');
        });
    }
};
