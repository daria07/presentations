<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->text('topic');
            $table->unsignedSmallInteger('slide_count')->default(8);

            // Уточняющие вопросы Claude и ответы пользователя
            $table->jsonb('clarifications')->nullable();
            // Структура слайдов, которую вернул Claude
            $table->jsonb('outline')->nullable();

            // draft | asking | queued | generating | ready | failed
            $table->string('status', 20)->default('draft')->index();

            $table->string('file_path')->nullable();
            $table->string('file_format', 10)->nullable();
            $table->string('share_token', 32)->unique();

            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
