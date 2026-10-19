<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
   Акция «Мы обновились» и служебные письма.

   Акция — скидка на сайте для тех, кто зарегистрировался до
   обновления. Отдельными полями, а не через поля кнопки скидки:
   у той свой A/B-тест и своя статистика, и смешивать их нельзя.

   mail_sends — кому какое письмо уже ушло: повторный запуск
   рассылки не пришлёт его второй раз.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Когда человек впервые увидел предложение — с этого момента идёт срок
            $table->timestamp('update_offer_at')->nullable();
            $table->timestamp('update_offer_until')->nullable();
            $table->timestamp('update_offer_used_at')->nullable();
            // Отписался от писем о новостях сервиса
            $table->timestamp('news_unsubscribed_at')->nullable();
        });

        Schema::create('mail_sends', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 50);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['campaign', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_sends');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['update_offer_at', 'update_offer_until', 'update_offer_used_at', 'news_unsubscribed_at']);
        });
    }
};
