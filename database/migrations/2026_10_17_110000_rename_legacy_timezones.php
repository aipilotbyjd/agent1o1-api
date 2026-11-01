<?php

use App\Services\Timezones\Timezones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store retired timezone names browsers report ("Asia/Calcutta") under their
 * current ones ("Asia/Kolkata"), now that `Timezones` only offers current
 * names. Same zones, so no schedule moves.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (Timezones::ALIASES as $retired => $current) {
            DB::table('workspaces')->where('timezone', $retired)->update(['timezone' => $current]);
            DB::table('assistant_triggers')->where('timezone', $retired)->update(['timezone' => $current]);
        }

        $this->renameInJson('triggers', 'config');
        $this->renameInJson('assistant_briefing_configs', 'schedule');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The current names are the same zones, so there's nothing to undo.
    }

    private function renameInJson(string $table, string $column): void
    {
        DB::table($table)
            ->whereIn("{$column}->timezone", array_keys(Timezones::ALIASES))
            ->orderBy('id')
            ->each(function (object $row) use ($table, $column): void {
                $value = json_decode($row->{$column}, true);
                $value['timezone'] = Timezones::ALIASES[$value['timezone']];

                DB::table($table)->where('id', $row->id)->update([$column => json_encode($value)]);
            });
    }
};
