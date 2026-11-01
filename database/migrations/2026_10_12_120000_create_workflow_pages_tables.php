<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plans that may share pages outside the workspace, and how many pages
     * each plan allows. Merged into existing rows rather than re-running
     * `PlansDataset`, which would overwrite anything edited since it last ran.
     *
     * @var array<string, array{public: bool, limit: int}>
     */
    private const array PLAN_GRANTS = [
        'free' => ['public' => false, 'limit' => 3],
        'starter' => ['public' => true, 'limit' => 25],
        'pro' => ['public' => true, 'limit' => -1],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('content');
            $table->json('published_content')->nullable();
            $table->json('variant_content')->nullable();
            $table->json('published_variant_content')->nullable();
            $table->unsignedTinyInteger('variant_split')->default(50);
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access')->default('workspace');
            $table->string('password')->nullable();
            $table->string('public_token', 48)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('max_runs_per_day')->nullable();
            // What a link to the page shows when it's pasted into a chat or
            // a post. Null = worked out from the page; see PageSharePreview.
            $table->string('share_title', 120)->nullable();
            $table->string('share_description', 300)->nullable();
            $table->string('share_image_url', 2048)->nullable();
            // Bumped whenever either draft changes, so a builder saving over
            // a draft someone else changed since it loaded is refused.
            $table->unsignedInteger('draft_revision')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'updated_at']);
            $table->index('workflow_id');
        });

        Schema::create('workflow_page_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('workflow_page_id')->constrained('workflow_pages')->cascadeOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 64)->nullable();
            $table->string('secret_hash', 64);
            $table->boolean('is_test')->default(false);
            $table->char('variant', 1)->default('a');
            $table->json('input')->nullable();
            $table->timestamps();

            $table->index(['workflow_page_id', 'created_at']);
        });

        Schema::create('workflow_page_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('workflow_page_id')->constrained('workflow_pages')->cascadeOnDelete();
            $table->char('variant', 1)->default('a');
            $table->string('type', 16);
            $table->string('visitor_hash', 64);
            $table->date('day');
            $table->timestamp('created_at')->nullable();

            $table->unique(['workflow_page_id', 'type', 'variant', 'visitor_hash', 'day'], 'workflow_page_events_unique_visit');
            $table->index(['workflow_page_id', 'day']);
        });

        foreach (self::PLAN_GRANTS as $slug => $grant) {
            $plan = DB::table('plans')->where('slug', $slug)->first(['id', 'features', 'limits']);

            if ($plan === null) {
                continue;
            }

            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $limits = json_decode($plan->limits ?? '[]', true) ?: [];

            if ($grant['public']) {
                $features['public_pages'] = true;
            }

            $limits['pages'] ??= $grant['limit'];

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode((object) $features),
                'limits' => json_encode((object) $limits),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_page_events');
        Schema::dropIfExists('workflow_page_submissions');
        Schema::dropIfExists('workflow_pages');
    }
};
