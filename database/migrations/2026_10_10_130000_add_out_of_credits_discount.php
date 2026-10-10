<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Скидка для тех, у кого закончились генерации, и A/B-тест текста
 * кнопки, которая её предлагает.
 *
 * У пользователя: какой текст ему достался, когда кнопку показали
 * впервые и когда по ней кликнули, до какого времени действует
 * скидка и когда она использована. У платежа — процент скидки,
 * чтобы считать оплаты по вариантам и видеть цену в истории.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('promo_variant', 1)->nullable();
            $table->timestamp('promo_shown_at')->nullable();
            $table->timestamp('promo_clicked_at')->nullable();
            $table->timestamp('discount_until')->nullable();
            $table->timestamp('discount_used_at')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'promo_variant', 'promo_shown_at', 'promo_clicked_at',
                'discount_until', 'discount_used_at',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
    }
};
