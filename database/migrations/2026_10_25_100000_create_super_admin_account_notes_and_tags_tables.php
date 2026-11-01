<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What operators write down about a customer: free-text notes ("asked for a
 * refund by email, approved") and short tags ("vip", "watch"). They are for
 * other admins only and are never shown to the customer.
 *
 * The customer is a user or a workspace, kept as a label plus its id like the
 * audit log does, so neither table is tied to a model class.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admin_account_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type', 32);
            $table->uuid('subject_id');
            $table->foreignUuid('super_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        Schema::create('super_admin_account_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type', 32);
            $table->uuid('subject_id');
            $table->string('tag', 24);
            $table->foreignUuid('super_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['subject_type', 'subject_id', 'tag']);
            $table->index('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_account_tags');
        Schema::dropIfExists('super_admin_account_notes');
    }
};
