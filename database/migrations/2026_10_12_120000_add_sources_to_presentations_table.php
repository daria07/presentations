<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Источники, найденные в Яндексе для презентации по теме: адрес,
 * заголовок и выжимка текста страницы. null — ещё не искали,
 * пустой массив — искали, но ничего пригодного не нашли. Храним,
 * чтобы повторная попытка генерации не платила за поиск второй раз.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->jsonb('sources')->nullable()->after('brief');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('sources');
        });
    }
};
