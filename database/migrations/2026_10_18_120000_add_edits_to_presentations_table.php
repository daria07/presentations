<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
   Правил ли человек презентацию в редакторе: сколько раз сохранял
   и когда последний раз. Для метрик — пользуются ли редактором.
   Смена темы и гаммы сюда не считается: это перепечатка, не правка.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->unsignedInteger('edits_count')->default(0);
            $table->timestamp('edited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn(['edits_count', 'edited_at']);
        });
    }
};
