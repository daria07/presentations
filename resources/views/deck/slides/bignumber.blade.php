@php($stat = ($slide['stats'] ?? [])[0] ?? null)

<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
    </div>
    <div class="body body--center">
        <div class="bignum">
            <div class="bignum-value">{{ $stat['value'] ?? '—' }}</div>

            @if (filled($stat['label'] ?? null))
                <div class="bignum-label">{{ $stat['label'] }}</div>
            @endif

            @if (filled($slide['subheading'] ?? null))
                <div class="bignum-note">{{ $slide['subheading'] }}</div>
            @endif
        </div>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
