<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 20)->default('stripe');
            // Уникальность защищает от повторного начисления,
            // если webhook придёт дважды
            $table->string('provider_payment_id')->unique();

            $table->unsignedInteger('amount');        // в копейках / центах
            $table->string('currency', 3)->default('RUB');
            $table->unsignedInteger('credits_granted')->default(0);

            // pending | paid | failed | refunded
            $table->string('status', 20)->default('pending')->index();

            $table->jsonb('payload')->nullable();     // сырой ответ провайдера
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
