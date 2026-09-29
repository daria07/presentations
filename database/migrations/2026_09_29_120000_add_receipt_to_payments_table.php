<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чек по налогу на профессиональный доход.
 *
 * ЮKassa закрыла автоматическую выдачу чеков самозанятым, поэтому чек
 * выбивается вручную в «Мой налог», а сюда кладётся ссылка на него или
 * загруженный файл — покупатель видит чек в истории платежей.
 *
 * Два поля, а не одно: ссылка из «Мой налог» копируется в два касания,
 * но живёт на стороне ФНС; файл надёжнее, но его нужно скачать. Пусть
 * будет и то и другое, заполняется любое.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_url', 500)->nullable()->after('payload');
            $table->string('receipt_path')->nullable()->after('receipt_url');
            $table->timestamp('receipt_added_at')->nullable()->after('receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_url', 'receipt_path', 'receipt_added_at']);
        });
    }
};
