{{-- Мягкая: округлый шрифт, много воздуха, надзаголовок без капса.
     Поля самые большие из трёх тем — 24/26/18 мм. --}}
    .slide { padding: 24mm 26mm 18mm; }

    h1 { font-size: 46pt; font-weight: 800; line-height: 1.1; }
    h2 { font-size: 27pt; font-weight: 800; line-height: 1.2; }

    /* Единственная тема без капслока в надзаголовке */
    .eyebrow {
        font-size: 10.2pt;
        letter-spacing: 0;
        text-transform: none;
        color: var(--muted);
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
        width: 11.9mm;
        height: 0.64mm;
        border-radius: 0.42mm;
        background: var(--accent);
        margin: 4.7mm 0 8.5mm;
    }

    .rule-accent { width: 13.5mm; height: 0.64mm; border-radius: 0.42mm; }

    .bullets { gap: 5mm; }
    .bullet { gap: 4.7mm; }

    .bullet-mark {
        width: 9.7mm;
        height: 9.7mm;
        border-radius: 4.9mm;
        background: var(--accent-soft);
        color: var(--accent-ink);
        font-size: 11.4pt;
        font-weight: 600;
    }

    .bullet-title { font-size: 13.2pt; font-weight: 600; line-height: 1.3; }
    .bullet-text { font-size: 11.4pt; line-height: 1.5; }

    .stat { border-top: 0.42mm solid var(--rule); }
    .stat-value { font-size: 27pt; font-weight: 800; }

    .tl-stem { width: 0.42mm; }
    .tl-value { font-size: 15.6pt; font-weight: 800; }

    .step, .cell, .compare-col { border-radius: 2.1mm; box-shadow: none; }
    .cell { background: var(--accent-soft); }

    .step-icon { color: var(--accent-ink); }
    .step-num { color: var(--muted); font-size: 10.2pt; }

    .bignum-value { font-size: 120pt; font-weight: 800; letter-spacing: -0.03em; }
    .quote-text { font-size: 22pt; font-weight: 600; line-height: 1.4; }
