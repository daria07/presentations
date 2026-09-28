<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Новая система гамм: восемь пастельных наборов вместо прежних насыщенных.
 * Старые ключи переводим на ближайшие по характеру, чтобы у готовых
 * презентаций не сбросилось оформление.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const MAP = [
        'coal' => 'graphite',
        'pine' => 'sage',
        'bordeaux' => 'wine',
        'sand' => 'clay',
        'ocean' => 'lagoon',
        'plum' => 'iris',
        'dopamine' => 'neon',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('presentations')->where('palette', $old)->update(['palette' => $new]);
        }

        // Через построитель схемы, а не сырым ALTER: у SQLite, на
        // которой гоняются тесты, такого синтаксиса нет вовсе, и
        // миграция падала в CI, работая при этом в PostgreSQL.
        Schema::table('presentations', function (Blueprint $table) {
            $table->string('palette', 20)->default('fog')->change();
        });
    }

    public function down(): void
    {
        // Уголь не разворачиваем: он слился с графитом, и отличить
        // бывший уголь от настоящего графита уже нечем — вернуть всех
        // в уголь значит испортить тех, кто графитом и был.
        foreach (self::MAP as $old => $new) {
            if ($new === 'graphite') {
                continue;
            }

            DB::table('presentations')->where('palette', $new)->update(['palette' => $old]);
        }

        Schema::table('presentations', function (Blueprint $table) {
            $table->string('palette', 20)->default('graphite')->change();
        });
    }
};
