<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Обложка рисуется отдельной задачей, параллельно со сборкой слайдов:
 * ей нужна только тема, а не структура. Здесь — её состояние:
 * {pending: true} пока рисуется, {path: …} когда готова,
 * {failed: true} если не вышло. null — отдельно не рисовали.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->jsonb('cover_image')->nullable()->after('sources');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
