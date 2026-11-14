{{--
    Объявление для всего сайта: над любой страницей, и для гостей, и для
    вошедших. Здесь, в общем шаблоне, а не во Vue — так оно одно на все
    раскладки (лендинг, вход, кабинет, документы) и видно сразу, до
    загрузки скриптов.

    Включается в config/app.php → site_notice (SITE_NOTICE в .env).
    Выключить: SITE_NOTICE=false, затем artisan config:cache.

    Стили инлайновые: Tailwind собирает классы из Vue, а этот файл —
    Blade; не хотим зависеть от того, попал ли класс в сборку.

    Коротко + «Подробнее»: полный текст раскрывается тут же, в плашке.
    Крестик прячет объявление у этого посетителя (localStorage).
    Новый текст — новая версия в NOTICE_KEY, и его снова увидят все.
--}}
<div id="site-notice" role="status"
     style="position:sticky;top:0;z-index:45;background:#fff4e5;border-bottom:1px solid #f0d3a8;color:#5c3b0f;font-size:14px;line-height:1.45;">
    <div style="max-width:1100px;margin:0 auto;padding:10px 52px 10px 16px;position:relative;">
        <div id="site-notice-short">
            <strong>Важно:</strong>
            из-за кибератаки на хостинг Beget часть данных утеряна, генерация
            иногда может завершаться ошибкой.
            <button type="button" onclick="window.__toggleSiteNotice(true)"
                    style="border:0;padding:0;background:none;color:inherit;font:inherit;font-weight:600;text-decoration:underline;text-underline-offset:2px;cursor:pointer;white-space:nowrap;">Подробнее</button>
        </div>
        <div id="site-notice-full" hidden>
            <strong>Важно о работе сервиса.</strong>
            Мы хранили данные у хостинг-провайдера Beget. В ночь на 2&nbsp;ноября на него
            обрушилась масштабная кибератака, и часть наших данных была утеряна.
            Сейчас мы восстанавливаем работу сервиса, поэтому генерация презентаций
            иногда может завершаться ошибкой.
            <ul style="margin:6px 0 0;padding-left:20px;list-style:disc;">
                <li>Если пропала ваша оплата или презентация, обязательно напишите
                    нам&nbsp;— мы начислим генерации.</li>
                <li>С&nbsp;вопросами, возвратом денег или по другой причине тоже
                    пишите в&nbsp;чат в&nbsp;личном кабинете.</li>
            </ul>
            <button type="button" onclick="window.__toggleSiteNotice(false)"
                    style="margin-top:4px;border:0;padding:0;background:none;color:inherit;font:inherit;font-weight:600;text-decoration:underline;text-underline-offset:2px;cursor:pointer;">Свернуть</button>
        </div>
        <button type="button" aria-label="Скрыть объявление"
                onclick="window.__closeSiteNotice()"
                style="position:absolute;top:4px;right:6px;width:40px;height:40px;border:0;border-radius:8px;background:transparent;color:inherit;font-size:28px;line-height:1;cursor:pointer;">&times;</button>
    </div>
</div>
<style>
    /* Боковое меню кабинета приколочено к экрану (fixed, на всю высоту):
       сдвигаем его под объявление, иначе оно закроет логотип */
    body.has-site-notice .fixed.inset-y-0.h-svh {
        top: var(--site-notice-h, 0px);
        height: calc(100svh - var(--site-notice-h, 0px));
    }
</style>
<script>
    (function () {
        var NOTICE_KEY = 'site-notice-closed-2026-11';
        var el = document.getElementById('site-notice');

        function closed() {
            try { return localStorage.getItem(NOTICE_KEY) === '1'; } catch (e) { return false; }
        }

        function measure() {
            document.body.style.setProperty('--site-notice-h', el.offsetHeight + 'px');
        }

        window.__toggleSiteNotice = function (open) {
            document.getElementById('site-notice-short').hidden = open;
            document.getElementById('site-notice-full').hidden = !open;
            measure();
        };

        window.__closeSiteNotice = function () {
            try { localStorage.setItem(NOTICE_KEY, '1'); } catch (e) {}
            el.remove();
            document.body.classList.remove('has-site-notice');
            document.body.style.removeProperty('--site-notice-h');
        };

        if (closed()) {
            el.remove();
            return;
        }

        document.body.classList.add('has-site-notice');
        measure();
        window.addEventListener('resize', measure);
    })();
</script>
