<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
   Чат с администратором. Диалог у человека один, поэтому отдельной
   таблицы диалогов нет: всё, что нужно, — его сообщения по user_id.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            // Чей это диалог — пользователя, а не автора сообщения:
            // ответы администратора лежат в диалоге того, кому он ответил
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            // Когда прочитала другая сторона: администратор — сообщение
            // человека, человек — ответ администратора
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['from_admin', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
