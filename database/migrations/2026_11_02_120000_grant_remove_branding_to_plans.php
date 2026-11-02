<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Plans that may hide the "Made with Agent1o1" badge on their workflow
     * pages. Merged into existing rows rather than re-running `PlansDataset`,
     * which would overwrite anything edited since it last ran.
     *
     * @var array<int, string>
     */
    private const array PLANS = ['pro'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->each(function (array $features): array {
            $features['remove_branding'] ??= true;

            return $features;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->each(function (array $features): array {
            unset($features['remove_branding']);

            return $features;
        });
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function each(callable $change): void
    {
        foreach (self::PLANS as $slug) {
            $plan = DB::table('plans')->where('slug', $slug)->first(['id', 'features']);

            if ($plan === null) {
                continue;
            }

            $features = json_decode($plan->features ?? '[]', true) ?: [];

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode((object) $change($features)),
            ]);
        }
    }
};
