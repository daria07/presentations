{{-- Обложка без украшений: заголовок и без того занимает её целиком,
     а узор и круги начинали спорить с длинным названием.
     Класс slide--bare гасит декоративные ::before и ::after. --}}
<div class="slide slide--cover slide--bare">
    <div class="rule-accent" style="margin-bottom: 12mm;"></div>
    <h1>{{ $slide['heading'] }}</h1>
    @if (filled($slide['subheading'] ?? null))
        <p class="subheading" style="font-size: 15pt; margin-top: 7mm;">{{ $slide['subheading'] }}</p>
    @endif
</div>
