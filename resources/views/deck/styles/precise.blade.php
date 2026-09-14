{{-- Строгая: прямые углы, волосяные линии, деловой тон.
     Значения из макета: 1 px холста 1600×900 = 0,6 pt = 0,2117 мм. --}}
    .slide { padding: 18mm 20mm 14mm; }

    h1 { font-size: 46pt; font-weight: 700; line-height: 1.06; letter-spacing: -0.01em; }
    h2 { font-size: 27pt; font-weight: 700; line-height: 1.15; letter-spacing: -0.005em; }

    .eyebrow {
        font-size: 9pt;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .subheading { font-size: 14pt; }

    /* Акцентная черта вместо подчёркивания заголовка */
    .head { border-bottom: 0; padding-bottom: 0; margin-bottom: 0; }
    .head::after {
        content: "";
        /* В базовой вёрстке черта лежит абсолютно под линейкой заголовка.
           Здесь линейки нет, черта становится обычным блоком — без сброса
           position её отступы игнорируются и она наезжает на заголовок. */
        position: static;
        display: block;
        width: 18.6mm;
        height: 0.42mm;
        background: var(--accent);
        margin: 4.7mm 0 9.7mm;
    }

    .rule-accent { width: 25.4mm; height: 0.42mm; border-radius: 0; }

    .bullets { gap: 5.5mm; }
    .bullet { gap: 5mm; }

    /* Квадратный маркер в тонкой рамке — без заливки и скруглений */
    .bullet-mark {
        width: 9.3mm;
        height: 9.3mm;
        border-radius: 0;
        border: 0.21mm solid var(--accent);
        background: transparent;
        color: var(--accent-ink);
        font-size: 11.4pt;
        font-weight: 600;
    }

    .bullet-title { font-size: 13.2pt; font-weight: 600; line-height: 1.25; }
    .bullet-text { font-size: 11.4pt; line-height: 1.45; }

    .stat { border-top: 0.42mm solid var(--rule); }
    .stat-value { font-size: 27pt; font-weight: 700; }
    .stat-label { font-size: 10.2pt; }

    .tl-dot { border-radius: 0; }
    .tl-stem { width: 0.21mm; }
    .tl-value { font-size: 15.6pt; font-weight: 700; }
    .tl-label { font-size: 10.2pt; }

    .step, .cell, .compare-col { border-radius: 0; box-shadow: none; }
    .cell, .step { border: 0.21mm solid var(--rule); background: transparent; }

    /* Иконка и номер — это просто значок и текст в потоке карточки.
       Фон им давать нельзя: ширина не задана, и плашка растянется
       во всю карточку. */
    .step-icon { color: var(--accent-ink); }
    .step-num { color: var(--accent-ink); font-size: 10.2pt; letter-spacing: .12em; }

    .bignum-value { font-size: 120pt; font-weight: 700; letter-spacing: -0.04em; }
    .quote-text { font-size: 21pt; font-weight: 500; }
