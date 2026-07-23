<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // The agent-type discriminator (App\Agents\Contracts\AgentType).
            // Everything stored today is a customer-created agent; internal
            // agents stay code-defined, but the column leaves room for future
            // DB-backed types (managed, marketplace, ...).
            $table->string('type', 20)->default('user')->index()->after('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
