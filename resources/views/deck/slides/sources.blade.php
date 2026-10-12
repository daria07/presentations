{{-- Источники, по которым писалась презентация. Ссылки настоящие:
     кликаются в просмотре и в PDF. Адрес выводим только http(s) —
     структура правится в редакторе, и проверка здесь последняя. --}}
@php($items = array_values(array_filter(
    $slide['sources'] ?? [],
    fn ($s) => is_array($s) && preg_match('#^https?://#i', (string) ($s['url'] ?? '')),
)))

<div class="slide">
    <div class="head">
        <h2>{{ $slide['heading'] }}</h2>
        @if (filled($slide['subheading'] ?? null))
            <p class="subheading">{{ $slide['subheading'] }}</p>
        @endif
    </div>
    <div class="body">
        <ol @class(['sources', 'sources--two' => count($items) > 5])>
            @foreach ($items as $n => $source)
                @php($host = preg_replace('/^www\./', '', (string) parse_url($source['url'], PHP_URL_HOST)))
                <li class="source">
                    <span class="source-mark">{{ $n + 1 }}</span>
                    <div class="source-body">
                        <a class="source-title" href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ filled($source['title'] ?? null) ? $source['title'] : $host }}</a>
                        <div class="source-host">{{ $host }}</div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
    <div class="pageno">{{ $index + 1 }} / {{ $total }}</div>
</div>
