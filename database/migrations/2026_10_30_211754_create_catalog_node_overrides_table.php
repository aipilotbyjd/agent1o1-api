<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A flag an admin puts on one built-in node: beta, deprecated, or hidden
     * from the library. The node class stays as it is; the catalog reads this.
     */
    public function up(): void
    {
        Schema::create('catalog_node_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('node_type')->unique();
            $table->string('status');
            $table->string('note', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_node_overrides');
    }
};
