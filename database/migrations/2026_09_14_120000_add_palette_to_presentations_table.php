<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Оформление разделилось на две оси: тема отвечает за характер
 * (шрифты, скругления, линии), гамма — только за цвет. Старые пять
 * шаблонов раскладываются на эти оси без потери смысла.
 */
return new class extends Migration
{
    private const MAP = [
        'graphite' => ['precise', 'graphite'],
        'ink' => ['precise', 'coal'],
        'plum' => ['precise', 'plum'],
        'forest' => ['soft', 'pine'],
        'clay' => ['soft', 'sand'],
    ];

    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->string('palette', 20)->default('graphite')->after('theme');
        });

        foreach (self::MAP as $old => [$theme, $palette]) {
            DB::table('presentations')
                ->where('theme', $old)
                ->update(['theme' => $theme, 'palette' => $palette]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => [$theme, $palette]) {
            DB::table('presentations')
                ->where('theme', $theme)
                ->where('palette', $palette)
                ->update(['theme' => $old]);
        }

        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('palette');
        });
    }
};
