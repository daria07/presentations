<?php

namespace App\Console\Commands;

use App\Models\Presentation;
use App\Models\User;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\PresentationPlanner;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Проверка связки с Claude без веб-интерфейса:
 *   php artisan claude:test "Пётр I" --slides=8
 */
class TestClaude extends Command
{
    protected $signature = 'claude:test
                            {topic? : Тема презентации}
                            {--slides=8 : Сколько слайдов}
                            {--keep : Не удалять презентацию после теста}
                            {--raw : Показать сырой ответ модели}';

    protected $description = 'Прогоняет оба запроса к Claude и показывает результат и стоимость';

    public function handle(): int
    {
        $user = User::query()->oldest()->first();

        if (! $user) {
            $this->error('В базе нет ни одного пользователя. Зарегистрируйся на сайте.');

            return self::FAILURE;
        }

        $topic = $this->argument('topic') ?: text('Тема презентации', required: true);

        $presentation = Presentation::create([
            'user_id' => $user->id,
            'topic' => $topic,
            'slide_count' => (int) $this->option('slides'),
        ]);

        try {
            $this->components->task('Спрашиваем уточнения', function () use ($presentation, &$questions) {
                $questions = PresentationPlanner::make()->askClarifyingQuestions($presentation);

                return true;
            });

            $answers = [];

            foreach ($questions as $q) {
                $answers[] = [
                    'key' => $q['key'],
                    'question' => $q['question'],
                    'answer' => select($q['question'], $q['options']),
                ];
            }

            $presentation->update(['clarifications' => $answers]);

            $planner = PresentationPlanner::make();

            $this->components->task('Собираем структуру', function () use ($planner, $presentation, &$outline) {
                $outline = $planner->buildOutline($presentation);

                return true;
            });

            // Сохраняем структуру — без этого deck:render её не найдёт
            $presentation->update([
                'outline' => $outline,
                'title' => $outline['title'] ?? null,
            ]);

            if ($this->option('raw')) {
                $this->newLine();
                $this->line(json_encode($planner->lastRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } catch (ClaudeException $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>'.$outline['title'].'</>');

        if (filled($outline['subtitle'] ?? null)) {
            $this->line('  <fg=gray>'.$outline['subtitle'].'</>');
        }

        $this->newLine();

        foreach ($outline['slides'] as $i => $slide) {
            $n = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $this->line("  <fg=yellow>{$n}</>  <options=bold>{$slide['heading']}</>  <fg=gray>[{$slide['layout']}]</>");

            foreach ($slide['bullets'] ?? [] as $b) {
                $this->line("       · <options=bold>{$b['title']}</> — {$b['text']}");
            }

            foreach ($slide['stats'] ?? [] as $s) {
                $this->line("       · <fg=cyan>{$s['value']}</> {$s['label']}");
            }

            if (filled($slide['quote']['text'] ?? null)) {
                $this->line("       « {$slide['quote']['text']} » — {$slide['quote']['author']}");
            }
        }

        $calls = $presentation->apiCalls()->get();
        $cost = $calls->sum('cost');

        $this->newLine();
        $this->components->twoColumnDetail('Слайдов', (string) count($outline['slides']));
        $this->components->twoColumnDetail('Токенов на вход', number_format($calls->sum('input_tokens'), 0, '', ' '));
        $this->components->twoColumnDetail('Токенов на выход', number_format($calls->sum('output_tokens'), 0, '', ' '));
        $this->components->twoColumnDetail('Стоимость', '$'.number_format($cost / 10000, 4));

        if (! $this->option('keep')) {
            $presentation->apiCalls()->delete();
            $presentation->delete();
        } else {
            $this->newLine();
            $this->components->info("Сохранено, id={$presentation->id}");
        }

        return self::SUCCESS;
    }
}
