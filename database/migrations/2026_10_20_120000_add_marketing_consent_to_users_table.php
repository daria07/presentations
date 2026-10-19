<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
   Согласие на рекламные письма (ст. 18 закона «О рекламе»).

   Хранится не флажком, а доказательством: когда, откуда (регистрация
   или настройки), с какого IP и на какой текст согласия. Если спросят,
   доказывать согласие придётся нам — без этого рассылка считается
   разосланной без него.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('marketing_consent_at')->nullable();
            $table->string('marketing_consent_ip', 45)->nullable();
            $table->string('marketing_consent_source', 20)->nullable();
            $table->string('marketing_consent_version', 20)->nullable();
            $table->timestamp('marketing_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'marketing_consent_at', 'marketing_consent_ip', 'marketing_consent_source',
                'marketing_consent_version', 'marketing_revoked_at',
            ]);
        });
    }
};
