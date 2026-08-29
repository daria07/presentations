<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained()->nullOnDelete();

            // clarify | outline | retry
            $table->string('purpose', 20);
            $table->string('model', 60);

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);

            // Себестоимость вызова в сотых доли цента
            $table->unsignedInteger('cost')->default(0);

            $table->timestamps();
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_calls');
    }
};
