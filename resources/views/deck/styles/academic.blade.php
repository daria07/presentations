{{-- Академический: нумерация разделов, сдержанный тон --}}
<style>
    .slide { padding: 20mm 26mm 16mm; }

    h1 { font-size: 40pt; font-weight: 600; line-height: 1.08; }
    h2 { font-size: 24pt; font-weight: 600; }

    .head {
        border-bottom: 0.4mm solid {{ $theme['rule'] }};
        padding-bottom: 5mm;
        margin-bottom: 10mm;
    }
    .head::after { width: 8mm; height: 0.4mm; }

    .subheading { font-size: 12.5pt; }

    /* Номер слайда крупнее и слева — как в оглавлении */
    .pageno {
        left: 26mm;
        right: auto;
        font-size: 10pt;
        font-family: "{{ $theme['font_display'] }}", serif;
    }

    .bullets { gap: 7mm; }

    .bullet-mark {
        background: transparent;
        border: 0.4mm solid {{ $theme['accent'] }};
        color: {{ $theme['accent'] }};
    }

    .bullet-title { font-weight: 600; }
    .bullet-text { font-size: 11.5pt; }

    .stat { border-top: 0.4mm solid {{ $theme['accent'] }}; }
    .stat-value { font-weight: 600; font-size: 34pt; }

    .quote-text { font-weight: 500; font-size: 20pt; }

    .step, .cell, .compare-col { border-radius: 1.5mm; }
    .cell { border-width: 0.4mm; }

    .slide--cover::before { display: none; }
</style>
