<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Чем оплачена генерация: trial — пробной, credit — купленной,
 * unlocked — пробной, а потом открыта за купленную.
 * Нужно в админке, чтобы видеть, к какой версии относится отзыв.
 *
 * Раньше это нигде не хранилось, поэтому прошлое восстанавливаем
 * по правилу, которое тогда действовало: пробная списывалась первой.
 * Значит, у тех, кто её потратил, самая ранняя готовая презентация
 * сделана по пробной (неудачная пробная возвращалась и тратилась
 * снова), а остальные готовые — за купленные.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->string('paid_with', 10)->nullable()->after('unlocked_at');
        });

        DB::table('presentations')
            ->whereNotNull('generated_at')
            ->update(['paid_with' => 'credit']);

        $firsts = DB::table('presentations')
            ->join('users', 'users.id', '=', 'presentations.user_id')
            ->where('users.trial_used', true)
            ->whereNotNull('presentations.generated_at')
            ->groupBy('presentations.user_id')
            ->selectRaw('min(presentations.id) as id')
            ->pluck('id');

        foreach ($firsts->chunk(500) as $ids) {
            DB::table('presentations')->whereIn('id', $ids->all())->update(['paid_with' => 'trial']);
        }
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropColumn('paid_with');
        });
    }
};
