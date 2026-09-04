{{-- Мягкий: крупные скругления, воздух, спокойный тон --}}
<style>
    .slide { padding: 22mm 26mm 18mm; }

    h1 { font-size: 42pt; line-height: 1.1; letter-spacing: -.02em; }
    h2 { font-size: 25pt; }

    /* Линейку под заголовком заменяем воздухом — она здесь лишняя */
    .head {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 12mm;
    }
    .head::after { display: none; }

    .bullets { gap: 6mm; }

    /* Пункты становятся карточками */
    .bullet {
        background: {{ $theme['accent_soft'] }};
        border-radius: 4mm;
        padding: 6mm 7mm;
        gap: 5mm;
    }
    .bullet-mark { background: {{ $theme['paper'] }}; }
    .bullet-text { max-width: 100%; }

    .step, .cell, .compare-col { border-radius: 5mm; }
    .cell { border: none; background: {{ $theme['accent_soft'] }}; }
    .cell:nth-child(2), .cell:nth-child(3) { background: {{ $theme['rule'] }}; }

    .stat { border-top: none; }
    .stat-value { color: {{ $theme['accent_ink'] }}; }

    .rule-accent { height: 2mm; border-radius: 2mm; }
    .slide--cover::after { border-width: 2mm; }
</style>
