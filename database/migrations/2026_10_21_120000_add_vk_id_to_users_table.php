<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
   Вход через VK ID: номер профиля VK, к которому привязан аккаунт.
   Уникальный — один профиль VK входит только в один аккаунт.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('vk_id', 32)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['vk_id']);
            $table->dropColumn('vk_id');
        });
    }
};
