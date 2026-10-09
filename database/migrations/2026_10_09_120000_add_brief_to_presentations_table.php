<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Конспект темы: содержательная заготовка, которую модель пишет
 * до разбивки по слайдам. Храним, чтобы не платить за него дважды
 * при повторной печати и чтобы было видно, из чего выросли слайды.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->jsonb('brief')->nullable()->after('clarifications');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('brief');
        });
    }
};
