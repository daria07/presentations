{{-- Контрастный: чёрное и белое, крупный шрифт, жирные линии --}}
<style>
    .slide { padding: 18mm 22mm 16mm; }

    h1 {
        font-size: 58pt;
        font-weight: 800;
        line-height: .98;
        letter-spacing: -.035em;
        text-transform: uppercase;
        max-width: 92%;
    }

    h2 {
        font-size: 32pt;
        font-weight: 800;
        letter-spacing: -.025em;
    }

    .head {
        border-bottom: 1.6mm solid {{ $theme['ink'] }};
        padding-bottom: 5mm;
        margin-bottom: 10mm;
    }
    .head::after { display: none; }

    .eyebrow { letter-spacing: .24em; }

    .bullet-mark {
        border-radius: 0;
        background: {{ $theme['ink'] }};
        color: {{ $theme['paper'] }};
    }

    .bullet-title { text-transform: uppercase; letter-spacing: .02em; }

    .stat { border-top: 1.6mm solid {{ $theme['ink'] }}; }
    .stat-value, .tl-value { letter-spacing: -.04em; }

    .bignum-value { font-size: 150pt; letter-spacing: -.06em; }

    .step, .cell, .compare-col { border-radius: 0; }
    .cell { border: 0.6mm solid {{ $theme['ink'] }}; }
    .compare-col { background: {{ $theme['accent_soft'] }}; }

    .timeline::before { opacity: 1; height: 1mm; }
    .tl-stem { opacity: 1; width: 1mm; }

    /* Обложке хватает типографики — декоративные круги убираем */
    .slide--cover::after, .slide--cover::before { display: none; }
    .rule-accent { width: 40mm; height: 3mm; border-radius: 0; }
</style>
