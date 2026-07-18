<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 'claude-sonnet-4-6' was never a real Anthropic model id, so every agent
 * created with the default model failed at request time with an uncaught
 * RequestException (surfaced to clients as a blank 500).
 */
return new class extends Migration
{
    private const OLD_DEFAULT = 'claude-sonnet-4-6';

    private const NEW_DEFAULT = 'claude-sonnet-5';

    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('model')->default(self::NEW_DEFAULT)->change();
        });

        Schema::table('agent_templates', function (Blueprint $table) {
            $table->string('llm_model')->default(self::NEW_DEFAULT)->change();
        });

        DB::table('agents')->where('model', self::OLD_DEFAULT)->update(['model' => self::NEW_DEFAULT]);
        DB::table('agent_templates')->where('llm_model', self::OLD_DEFAULT)->update(['llm_model' => self::NEW_DEFAULT]);
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('model')->default(self::OLD_DEFAULT)->change();
        });

        Schema::table('agent_templates', function (Blueprint $table) {
            $table->string('llm_model')->default(self::OLD_DEFAULT)->change();
        });
    }
};
