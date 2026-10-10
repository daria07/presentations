<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Оценка готовой презентации: звёзды от 1 до 5 и короткий отзыв.
 * Прямо в презентации, а не отдельной таблицей: у каждой презентации
 * не больше одной оценки, и человек может её поменять.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('error_message');
            $table->text('review')->nullable()->after('rating');
            $table->timestamp('reviewed_at')->nullable()->after('review');
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['rating', 'review', 'reviewed_at']);
        });
    }
};
