<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
{!! $fontCss !!}
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
        font-family: "{{ $theme['font_body'] }}", -apple-system, sans-serif;
        color: {{ $theme['ink'] }};
    }

    .slide {
        width: {{ $width }}mm;
        height: {{ $height }}mm;
        page-break-after: always;
        position: relative;
        overflow: hidden;
        background: {{ $theme['paper'] }};
        padding: 20mm 24mm 16mm;
        display: flex;
        flex-direction: column;
    }
    .slide:last-child { page-break-after: auto; }

    .slide--cover {
        background: {{ $theme['cover_bg'] }};
        color: {{ $theme['cover_ink'] }};
        justify-content: center;
    }

    /* ---------- типографика ---------- */

    h1 {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-weight: 800;
        font-size: 46pt;
        line-height: 1.06;
        letter-spacing: -0.02em;
        max-width: 84%;
        text-wrap: balance;
    }

    h2 {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-weight: 700;
        font-size: 27pt;
        line-height: 1.12;
        letter-spacing: -0.015em;
        max-width: 82%;
        text-wrap: balance;
    }

    .subheading {
        font-size: 13pt;
        color: {{ $theme['muted'] }};
        margin-top: 4mm;
        max-width: 70%;
        line-height: 1.45;
    }

    .slide--cover .subheading { color: rgba(255,255,255,.72); }

    .head {
        border-bottom: 0.5mm solid {{ $theme['rule'] }};
        padding-bottom: 6mm;
        margin-bottom: 9mm;
        flex: none;
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
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 9pt;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: {{ $theme['accent'] }};
        margin-bottom: 5mm;
    }
    .slide--cover .eyebrow { color: {{ $theme['cover_accent'] }}; }

    .pageno {
        position: absolute;
        right: 24mm;
        bottom: 9mm;
        font-size: 9pt;
        color: {{ $theme['muted'] }};
        font-variant-numeric: tabular-nums;
    }

    .rule-accent {
        width: 22mm;
        height: 1.2mm;
        background: {{ $theme['accent'] }};
        border-radius: 1mm;
    }
    .slide--cover .rule-accent { background: {{ $theme['cover_accent'] }}; }

    /* ---------- bullets ---------- */

    .bullets { display: flex; flex-direction: column; gap: 8mm; padding-top: 2mm; }

    .bullet { display: flex; gap: 6mm; align-items: flex-start; }

    .bullet-mark {
        flex: none;
        width: 7mm; height: 7mm;
        border-radius: 50%;
        background: {{ $theme['accent_soft'] }};
        color: {{ $theme['accent'] }};
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 9.5pt;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        margin-top: 0.8mm;
    }

    .bullet-title {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 13.5pt;
        font-weight: 700;
        margin-bottom: 1.5mm;
    }

    .bullet-text {
        font-size: 12pt;
        line-height: 1.5;
        color: {{ $theme['muted'] }};
        max-width: 82%;
    }

    /* ---------- stats ---------- */

    .stats { display: flex; gap: 8mm; }

    .stat {
        flex: 1;
        border-top: 1mm solid {{ $theme['accent'] }};
        padding-top: 6mm;
    }

    .stat-value {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 38pt;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .stat-label {
        font-size: 11.5pt;
        color: {{ $theme['muted'] }};
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
        background: {{ $theme['accent'] }};
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
        background: {{ $theme['accent'] }};
        flex: none;
        box-shadow: 0 0 0 2mm {{ $theme['paper'] }};
    }

    /* Ножка от точки к году — связывает линию с подписью */
    .tl-stem {
        width: 0.5mm;
        height: 9mm;
        background: {{ $theme['accent'] }};
        opacity: .3;
        margin-bottom: 5mm;
    }

    .tl-value {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 26pt;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .tl-label {
        font-size: 11.5pt;
        color: {{ $theme['muted'] }};
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
        background: {{ $theme['accent_soft'] }};
        display: flex;
        flex-direction: column;
    }
    .compare-col + .compare-col {
        background: {{ $theme['cover_bg'] }};
        color: #FFFFFF;
    }

    .compare-title {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 13pt;
        font-weight: 700;
        margin-bottom: 4mm;
        color: {{ $theme['accent'] }};
    }
    .compare-col + .compare-col .compare-title { color: {{ $theme['cover_accent'] }}; }

    .compare-text { font-size: 12pt; line-height: 1.5; }
    .compare-col .compare-text { color: {{ $theme['ink'] }}; }
    .compare-col + .compare-col .compare-text { color: rgba(255,255,255,.85); }

    /* ---------- quote ---------- */

    .quote-mark {
        font-family: Georgia, serif;
        font-size: 80pt;
        line-height: 0.6;
        color: {{ $theme['accent'] }};
        opacity: .28;
        margin-bottom: 2mm;
    }

    .quote-text {
        font-family: "{{ $theme['font_display'] }}", sans-serif;
        font-size: 22pt;
        font-weight: 500;
        line-height: 1.32;
        letter-spacing: -0.01em;
        max-width: 84%;
    }

    .quote-author {
        font-size: 12pt;
        color: {{ $theme['muted'] }};
        margin-top: 7mm;
    }
</style>
</head>
<body>
@foreach ($slides as $i => $slide)
    @includeFirst(
        ['deck.slides.'.$slide['layout'], 'deck.slides.bullets'],
        ['slide' => $slide, 'index' => $i, 'total' => count($slides), 'deckTitle' => $title]
    )
@endforeach
</body>
</html>
