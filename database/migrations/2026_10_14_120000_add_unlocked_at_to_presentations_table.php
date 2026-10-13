<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Полная версия презентации. Бесплатная (по пробной генерации) видна
 * только на сайте: с водяным знаком, без скачивания, ссылки, речи и
 * редактора. Полной становится, если её сделали за платную генерацию
 * или открыли потом за одну генерацию из пакета.
 *
 * Всё, что сделано до этого изменения, считаем полным: людям обещали
 * другое, отбирать уже выданное нечестно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->timestamp('unlocked_at')->nullable()->after('generated_at');
        });

        DB::table('presentations')->update(['unlocked_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('unlocked_at');
        });
    }
};
