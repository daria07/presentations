{{-- Модная: крупный гротеск, скругления 4 мм, мягкие тени.
     Unbounded очень широкий, поэтому заголовки короче по строке. --}}
    .slide { padding: 20mm 24mm 16mm; }

    h1 {
        font-size: 52pt;
        font-weight: 800;
        line-height: 1.02;
        letter-spacing: -0.02em;
        max-width: 74%;
    }

    h2 {
        font-size: 32pt;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.015em;
        max-width: 80%;
    }

    .eyebrow {
        font-size: 9pt;
        letter-spacing: .22em;
        text-transform: uppercase;
        color: var(--accent-ink);
    }

    .subheading { font-size: 14.4pt; }

    .head { border-bottom: 0; padding-bottom: 0; margin-bottom: 0; }
    .head::after {
        content: "";
        /* В базовой вёрстке черта лежит абсолютно под линейкой заголовка.
           Здесь линейки нет, черта становится обычным блоком — без сброса
           position её отступы игнорируются и она наезжает на заголовок. */
        position: static;
        display: block;
        width: 15.2mm;
        height: 1.27mm;
        border-radius: 0.64mm;
        background: var(--accent);
        margin: 5mm 0 9.3mm;
    }

    .rule-accent { width: 20.3mm; height: 1.27mm; border-radius: 0.64mm; }

    .bullets { gap: 4.7mm; }

    /* Пункт — карточка с тенью, а не строка списка */
    .bullet {
        gap: 5mm;
        align-items: flex-start;
        background: var(--paper);
        border-radius: 3.8mm;
        box-shadow: 0 2.1mm 6.4mm rgba(16, 19, 25, .08);
        padding: 4.2mm 5.5mm;
    }

    .bullet-mark {
        width: 10.2mm;
        height: 10.2mm;
        border-radius: 5.1mm;
        background: var(--accent);
        color: var(--paper);
        font-size: 11.4pt;
        font-weight: 700;
    }

    .bullet-mark--icon { background: var(--accent); color: var(--paper); }

    .bullet-title { font-size: 13.2pt; font-weight: 700; line-height: 1.25; }
    .bullet-text { font-size: 11.4pt; line-height: 1.45; }

    .stat {
        border-top: 1.27mm solid var(--accent);
        border-radius: 0;
    }
    .stat-value { font-size: 30pt; font-weight: 800; letter-spacing: -0.03em; }

    .tl-stem { width: 0.64mm; }
    .tl-value { font-size: 17pt; font-weight: 800; }

    .step, .cell, .compare-col {
        border-radius: 3.8mm;
        box-shadow: 0 2.1mm 6.4mm rgba(16, 19, 25, .08);
    }

    .cell { background: var(--accent-soft); }

    /* Кружок только у иконки, и с явным размером — иначе фон
       растянется на всю ширину карточки */
    .step-icon {
        width: 12mm;
        height: 12mm;
        border-radius: 6mm;
        background: var(--accent);
        color: var(--paper);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 4mm;
    }

    .step-num {
        color: var(--accent-ink);
        font-size: 10.2pt;
        letter-spacing: .14em;
    }

    .bignum-value { font-size: 140pt; font-weight: 800; letter-spacing: -0.05em; }
    .quote-text { font-size: 24pt; font-weight: 700; letter-spacing: -0.01em; }
