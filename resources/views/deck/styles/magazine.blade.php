{{-- Журнальный: засечки в заголовках, тёплая бумага, разворот --}}
<style>
    .slide { padding: 18mm 24mm 16mm; }

    h1 {
        font-size: 46pt;
        font-weight: 700;
        line-height: 1.02;
        letter-spacing: -.015em;
    }

    h2 {
        font-size: 27pt;
        font-weight: 600;
        letter-spacing: -.008em;
    }

    /* Двойная линейка под заголовком — типичный журнальный приём */
    .head {
        border-bottom: 1.4mm double {{ $theme['rule'] }};
        padding-bottom: 5mm;
    }
    .head::after { display: none; }

    .eyebrow { letter-spacing: .2em; }

    .subheading {
        font-style: italic;
        font-size: 14pt;
    }

    .bullet-title { letter-spacing: 0; }

    /* Курсивная подпись у чисел смягчает строгость колонок */
    .stat-label, .tl-label { font-style: italic; }

    .stat { border-top: 0.6mm solid {{ $theme['ink'] }}; }

    .quote-text { font-style: italic; font-weight: 500; }
    .quote-mark { opacity: .4; font-size: 100pt; }

    .step, .cell, .compare-col { border-radius: 1mm; }
</style>
