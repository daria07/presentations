<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Начало и конец генерации — для админки: сколько человек ждал.
 *
 * generated_at для этого не годится: его переписывает каждая
 * перепечатка (смена темы, скрытие источников, открытие полной версии).
 *
 * Прошлое восстанавливаем приблизительно по журналу запросов к
 * нейросетям: от первого запроса после уточняющих вопросов до
 * последнего (обычно — последней картинки). Печать PDF в конце
 * сюда не попадает, это ещё секунд 10–20.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->timestamp('generation_started_at')->nullable()->after('generated_at');
            $table->timestamp('generation_finished_at')->nullable()->after('generation_started_at');
        });

        DB::table('presentations')
            ->whereNotNull('generated_at')
            ->update([
                'generation_started_at' => DB::raw(
                    "(select min(api_calls.created_at) from api_calls where api_calls.presentation_id = presentations.id and api_calls.purpose <> 'clarify')"
                ),
                'generation_finished_at' => DB::raw(
                    '(select max(api_calls.created_at) from api_calls where api_calls.presentation_id = presentations.id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['generation_started_at', 'generation_finished_at']);
        });
    }
};
