<!DOCTYPE html>
<html lang="ru" data-palette="{{ $paletteKey }}" data-theme="{{ $themeKey }}" data-style="{{ $style }}">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
{!! $fontCss !!}
</style>

{{-- Палитры всех тем сразу. Переключение на экране — это смена
     data-theme на <html>, без перерисовки и без запроса к серверу. --}}
<style>
{!! $themeVars !!}
</style>
<style>
    @page {
        size: {{ $width }}mm {{ $height }}mm;
        margin: 0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-family: var(--font-body), -apple-system, sans-serif;
        color: var(--ink);
    }

    .slide {
        width: {{ $width }}mm;
        height: {{ $height }}mm;
        page-break-after: always;
        position: relative;
        overflow: hidden;
        background: var(--paper);
        padding: 20mm 24mm 16mm;
        display: flex;
        flex-direction: column;
    }
    .slide:last-child { page-break-after: auto; }

    .slide--cover {
        background: var(--cover-bg);
        color: var(--cover-ink);
        justify-content: center;
    }

    /* ---------- типографика ---------- */

    h1 {
        font-family: var(--font-display), sans-serif;
        font-weight: 800;
        font-size: 46pt;
        line-height: 1.06;
        letter-spacing: -0.02em;
        max-width: 84%;
        text-wrap: balance;
    }

    h2 {
        font-family: var(--font-display), sans-serif;
        font-weight: 700;
        font-size: 27pt;
        line-height: 1.12;
        letter-spacing: -0.015em;
        max-width: 82%;
        text-wrap: balance;
    }

    .subheading {
        font-size: 13pt;
        color: var(--muted);
        margin-top: 4mm;
        max-width: 70%;
        line-height: 1.45;
    }

    .slide--cover .subheading { color: rgba(255,255,255,.72); }

    .head {
        border-bottom: 0.5mm solid var(--rule);
        padding-bottom: 6mm;
        margin-bottom: 9mm;
        flex: none;
        position: relative;
    }

    /* Короткий цветной отрезок поверх линейки — связывает слайды
       между собой, не перетягивая внимание на себя */
    .head::after {
        content: "";
        position: absolute;
        left: 0;
        bottom: -0.5mm;
        width: 16mm;
        height: 0.5mm;
        background: var(--accent);
    }

    /*
       Текст читается сверху вниз, поэтому содержательная часть начинается
       сразу под заголовком, а свободное место остаётся снизу. Слайды с
       одним цельным объектом — цифрами, таймлайном, цитатой — наоборот
       выигрывают от центрирования.
    */
    .body { flex: 1; display: flex; flex-direction: column; justify-content: flex-start; }
    .body--center { justify-content: center; }

    /* ---------- метки и номера ---------- */

    .eyebrow {
        font-family: var(--font-display), sans-serif;
        font-size: 9pt;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 5mm;
    }
    .slide--cover .eyebrow { color: var(--cover-accent); }

    .pageno {
        position: absolute;
        right: 24mm;
        bottom: 9mm;
        font-size: 9pt;
        color: var(--muted);
        font-variant-numeric: tabular-nums;
    }

    .rule-accent {
        width: 22mm;
        height: 1.2mm;
        background: var(--accent);
        border-radius: 1mm;
    }
    .slide--cover .rule-accent { background: var(--cover-accent); }

    /* ---------- bullets ---------- */

    .bullets { display: flex; flex-direction: column; gap: 8mm; padding-top: 2mm; }

    .bullet { display: flex; gap: 6mm; align-items: flex-start; }

    .bullet-mark {
        flex: none;
        width: 7mm; height: 7mm;
        border-radius: 50%;
        background: var(--accent-soft);
        color: var(--accent);
        font-family: var(--font-display), sans-serif;
        font-size: 9.5pt;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        margin-top: 0.8mm;
    }

    /* Иконка вместо номера: кружок тот же, содержимое другое */
    .bullet-mark--icon {
        background: transparent;
        color: var(--accent);
        width: 8mm;
        height: 8mm;
        margin-top: 0;
    }

    .step-icon {
        color: var(--accent);
        margin-bottom: 4mm;
    }

    .cell-icon {
        color: var(--accent);
        margin-bottom: 3.5mm;
    }

    .bullet-title {
        font-family: var(--font-display), sans-serif;
        font-size: 13.5pt;
        font-weight: 700;
        margin-bottom: 1.5mm;
    }

    .bullet-text {
        font-size: 12pt;
        line-height: 1.5;
        color: var(--muted);
        max-width: 82%;
    }

    /* ---------- stats ---------- */

    .stats { display: flex; gap: 8mm; }

    .stat {
        flex: 1;
        border-top: 1mm solid var(--accent);
        padding-top: 6mm;
    }

    .stat-value {
        font-family: var(--font-display), sans-serif;
        font-size: 38pt;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .stat-label {
        font-size: 11.5pt;
        color: var(--muted);
        margin-top: 3mm;
        line-height: 1.4;
    }

    /* ---------- timeline ---------- */

    .timeline {
        display: flex;
        position: relative;
        padding: 14mm 0 6mm;
        align-items: flex-start;
    }

    /* Основная линия — в цвете акцента, приглушённая */
    .timeline::before {
        content: "";
        position: absolute;
        top: 15.8mm;
        left: 5%;
        right: 5%;
        height: 0.5mm;
        background: var(--accent);
        opacity: .22;
    }

    .tl-item {
        flex: 1;
        text-align: center;
        position: relative;
        padding: 0 4mm;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .tl-dot {
        width: 5mm;
        height: 5mm;
        border-radius: 50%;
        background: var(--accent);
        flex: none;
        box-shadow: 0 0 0 2mm var(--paper);
    }

    /* Ножка от точки к году — связывает линию с подписью */
    .tl-stem {
        width: 0.5mm;
        height: 9mm;
        background: var(--accent);
        opacity: .3;
        margin-bottom: 5mm;
    }

    .tl-value {
        font-family: var(--font-display), sans-serif;
        font-size: 26pt;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .tl-label {
        font-size: 11.5pt;
        color: var(--muted);
        margin-top: 4mm;
        line-height: 1.45;
        max-width: 46mm;
    }

    /* ---------- comparison ---------- */

    .compare { display: flex; gap: 10mm; align-items: stretch; }

    /* Минимальная высота не даёт коротким текстам выглядеть обрубками */
    .compare-col {
        flex: 1;
        min-height: 52mm;
        padding: 10mm;
        border-radius: 3mm;
        background: var(--accent-soft);
        display: flex;
        flex-direction: column;
    }
    .compare-col + .compare-col {
        background: var(--cover-bg);
        color: #FFFFFF;
    }

    .compare-title {
        font-family: var(--font-display), sans-serif;
        font-size: 13pt;
        font-weight: 700;
        margin-bottom: 4mm;
        /* Мелкий текст красим только в accent_ink: чистый accent у ярких
           гамм даёт около 3:1 к бумаге — годится для крупного и для
           линий, но не для подписи в 13 пунктов */
        color: var(--accent-ink, var(--accent));
    }
    .compare-col + .compare-col .compare-title { color: var(--cover-accent); }

    .compare-text { font-size: 12pt; line-height: 1.5; }
    .compare-col .compare-text { color: var(--ink); }
    .compare-col + .compare-col .compare-text { color: rgba(255,255,255,.85); }


    /* ---------- process: этапы со стрелками ---------- */

    .process { display: flex; align-items: stretch; gap: 0; }

    .step {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 9mm 7mm;
        background: var(--accent-soft);
        border-radius: 2.5mm;
        position: relative;
    }

    /* Стрелка между этапами — треугольник из границ, без картинок */
    .step + .step { margin-left: 7mm; }

    .step + .step::before {
        content: "";
        position: absolute;
        left: -5.6mm;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-top: 2.4mm solid transparent;
        border-bottom: 2.4mm solid transparent;
        border-left: 3.2mm solid var(--accent);
        opacity: .5;
    }

    .step-num {
        font-family: var(--font-display), sans-serif;
        font-size: 10pt;
        font-weight: 700;
        color: var(--accent);
        letter-spacing: .1em;
        margin-bottom: 4mm;
        font-variant-numeric: tabular-nums;
    }

    .step-title {
        font-family: var(--font-display), sans-serif;
        font-size: 13pt;
        font-weight: 700;
        margin-bottom: 3mm;
    }

    .step-text { font-size: 11pt; line-height: 1.45; color: var(--muted); }

    /* ---------- matrix: четыре поля по двум осям ---------- */

    .matrix {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6mm;
    }

    .cell {
        padding: 9mm;
        border-radius: 2.5mm;
        border: 0.4mm solid var(--rule);
        display: flex;
        flex-direction: column;
        min-height: 40mm;
    }

    /* По диагонали заливаем — сетка читается как матрица, а не как список */
    .cell:nth-child(1), .cell:nth-child(4) {
        background: var(--accent-soft);
        border-color: transparent;
    }

    .cell-title {
        font-family: var(--font-display), sans-serif;
        font-size: 12.5pt;
        font-weight: 700;
        margin-bottom: 3mm;
    }
    .cell:nth-child(1) .cell-title, .cell:nth-child(4) .cell-title {
        color: var(--accent-ink, var(--accent));
    }

    .cell-text { font-size: 11pt; line-height: 1.45; color: var(--muted); }

    /* ---------- bignumber: одно число во весь слайд ---------- */

    .bignum {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .bignum-value {
        font-family: var(--font-display), sans-serif;
        font-size: 120pt;
        font-weight: 800;
        line-height: .88;
        letter-spacing: -.045em;
        color: var(--accent);
        font-variant-numeric: tabular-nums;
    }

    .bignum-label {
        font-family: var(--font-display), sans-serif;
        font-size: 19pt;
        font-weight: 700;
        margin-top: 6mm;
        max-width: 70%;
    }

    .bignum-note {
        font-size: 12.5pt;
        line-height: 1.5;
        color: var(--muted);
        margin-top: 4mm;
        max-width: 62%;
    }

    /* ---------- фоновый мотив ---------- */

    /* ---------- декоративная геометрия обложки ---------- */

    /* Дуга в углу: намёк на объём, который не спорит с текстом */
    .slide--bare::after,
    .slide--bare::before { display: none; }

    .slide--cover::after {
        content: "";
        position: absolute;
        right: -30mm;
        bottom: -46mm;
        width: 130mm;
        height: 130mm;
        border-radius: 50%;
        border: 1mm solid var(--cover-accent);
        opacity: .16;
    }

    .slide--cover::before {
        content: "";
        position: absolute;
        right: 14mm;
        bottom: 22mm;
        width: 34mm;
        height: 34mm;
        border-radius: 50%;
        background: var(--cover-accent);
        opacity: .1;
    }

    /* ---------- quote ---------- */


    .quote-mark {
        font-family: Georgia, serif;
        font-size: 80pt;
        line-height: 0.6;
        color: var(--accent);
        opacity: .28;
        margin-bottom: 2mm;
    }

    .quote-text {
        font-family: var(--font-display), sans-serif;
        font-size: 22pt;
        font-weight: 500;
        line-height: 1.32;
        letter-spacing: -0.01em;
        max-width: 84%;
    }

    .quote-author {
        font-size: 12pt;
        color: var(--muted);
        margin-top: 7mm;
    }
</style>

{{-- Характер шаблона: типографика, композиция, форма элементов.
     Подключаем все наборы сразу, каждый под своим data-style — тогда
     смена темы на экране меняет и цвета, и типографику, не трогая сервер. --}}
@foreach ($styles as $styleKey)
<style>
{{-- :where() не добавляет веса селекторам внутри, поэтому правила
     шаблона спорят с базовой вёрсткой ровно так же, как раньше.
     Без него .compare-col из шаблона перебивал .compare-col + .compare-col
     из базы — и вторая колонка теряла тёмный фон. --}}
:where([data-style="{{ $styleKey }}"]) {
@include('deck.styles.'.$styleKey)
}
</style>
@endforeach

{{-- Экранный просмотр собирается на стороне приложения: слайды
     переносятся в Shadow DOM страницы, а лента миниатюр, стрелки и
     полный экран — обычный интерфейс. Здесь остаётся только вёрстка. --}}
</head>
<body>
<div class="deck" data-palette="{{ $paletteKey }}" data-theme="{{ $themeKey }}" data-style="{{ $style }}">
@foreach ($slides as $i => $slide)
    @includeFirst(
        ['deck.slides.'.$slide['layout'], 'deck.slides.bullets'],
        ['slide' => $slide, 'index' => $i, 'total' => count($slides), 'deckTitle' => $title]
    )
@endforeach
</div>
</body>
</html>
