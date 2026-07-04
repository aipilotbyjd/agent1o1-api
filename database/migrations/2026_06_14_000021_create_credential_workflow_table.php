<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_workflow', function (Blueprint $table) {
            $table->foreignUuid('credential_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->primary(['credential_id', 'workflow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_workflow');
    }
};
