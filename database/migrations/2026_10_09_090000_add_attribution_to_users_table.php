<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Источник регистрации: с какими метками человек впервые пришёл.
 * Отдельные колонки, а не json: по кампании нужно будет группировать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('click_id')->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->string('landing_url', 1000)->nullable();

            $table->index('utm_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['utm_campaign']);
            $table->dropColumn([
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content',
                'utm_term', 'click_id', 'referrer', 'landing_url',
            ]);
        });
    }
};
