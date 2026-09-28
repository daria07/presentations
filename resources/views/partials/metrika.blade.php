{{--
    Яндекс Метрика.

    Подключается только когда в .env задан YANDEX_METRIKA_ID — локально
    и в тестах счётчика нет, иначе статистика мешалась бы с разработкой.

    Этот файл включается в resources/views/app.blade.php — раскладку
    самого приложения. В resources/views/deck/deck.blade.php, по которой
    Chrome печатает PDF на сервере, счётчика нет и быть не должно:
    иначе каждая напечатанная презентация считалась бы посещением.

    Счётчик знает только первую загрузку страницы; переходы внутри
    приложения Inertia делает без перезагрузки, и о них Метрике
    сообщает resources/js/app.ts — id берёт отсюда, из window.
--}}
@if ($metrikaId = config('services.metrika.id'))
    <script>window.__metrikaId = {{ (int) $metrikaId }};</script>
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id={{ $metrikaId }}', 'ym');

        ym({{ (int) $metrikaId }}, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/{{ $metrikaId }}" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
@endif
