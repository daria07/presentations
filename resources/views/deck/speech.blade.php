<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>{{ $title }} — речь докладчика</title>
<style>
{!! $fontCss !!}

    /* Поля задаёт Browsershot: страниц несколько, и паддинг на потоке
       достался бы только первой и последней, а со второй текст упёрся бы
       в край листа. Здесь их не дублируем — puppeteer всё равно
       перебивает @page своими значениями */
    @page { size: 210mm 297mm; }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-family: "{{ $fontBody }}", -apple-system, sans-serif;
        color: #16181C;
        background: #FFFFFF;
    }



    /* ---------- шапка ---------- */

    .eyebrow {
        font-size: 8.5pt;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: {{ $accent }};
    }

    h1 {
        font-family: "{{ $fontDisplay }}", sans-serif;
        font-size: 22pt;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.015em;
        margin-top: 3mm;
        max-width: 90%;
        text-wrap: balance;
    }

    .meta {
        font-size: 10pt;
        color: #66707C;
        margin-top: 3mm;
        padding-bottom: 7mm;
        border-bottom: 0.4mm solid #DCE2E8;
    }

    /* ---------- слайд ---------- */

    /* Реплику не рвём между страницами: докладчик читает её целиком */
    .item {
        display: flex;
        gap: 7mm;
        padding: 7mm 0;
        border-bottom: 0.2mm solid #E7EAEE;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .item:last-child { border-bottom: 0; }

    .num {
        flex: none;
        width: 12mm;
        font-family: "{{ $fontDisplay }}", sans-serif;
        font-size: 15pt;
        font-weight: 700;
        line-height: 1.1;
        color: {{ $accent }};
        font-variant-numeric: tabular-nums;
    }

    .body { flex: 1; min-width: 0; }

    .heading {
        font-family: "{{ $fontDisplay }}", sans-serif;
        font-size: 12.5pt;
        font-weight: 700;
        line-height: 1.25;
        margin-bottom: 2.5mm;
    }

    .time {
        font-size: 8.5pt;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #8A929C;
        margin-bottom: 2.5mm;
    }

    /* Кегль и интерлиньяж выбраны под чтение с листа в руках,
       а не под экономию бумаги: с трибуны глаз должен попадать
       в нужную строку с первого раза */
    .say {
        font-size: 12pt;
        line-height: 1.65;
        max-width: 155mm;
    }

    .empty { color: #8A929C; font-style: italic; }

    .tail {
        margin-top: 9mm;
        padding-top: 6mm;
        border-top: 0.4mm solid #DCE2E8;
        font-size: 9.5pt;
        line-height: 1.5;
        color: #66707C;
    }
</style>
</head>
<body>
<div class="sheet">
    <div class="eyebrow">Речь докладчика</div>
    <h1>{{ $title }}</h1>
    <p class="meta">
        {{ $slideCount }} {{ $slideWord }} · примерно {{ $totalMinutes }} {{ $minuteWord }} вслух
    </p>

    @foreach ($items as $item)
        <div class="item">
            <div class="num">{{ $item['number'] }}</div>
            <div class="body">
                <p class="heading">{{ $item['heading'] }}</p>
                @if ($item['seconds'])
                    <p class="time">≈ {{ $item['seconds'] }} с</p>
                @endif
                @if ($item['notes'])
                    <p class="say">{{ $item['notes'] }}</p>
                @else
                    <p class="say empty">Заметки к этому слайду не заполнены.</p>
                @endif
            </div>
        </div>
    @endforeach

    <p class="tail">
        Время посчитано по 130 словам в минуту — это спокойный темп без спешки.
        Паузы, вопросы из зала и переключение слайдов сюда не входят,
        поэтому на выступление стоит закладывать примерно вдвое больше.
    </p>
</div>
</body>
</html>
