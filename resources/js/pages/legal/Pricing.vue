<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Layout from './Layout.vue';

/**
 * Открытая страница тарифов.
 *
 * Нужна платёжному провайдеру: цены, состав услуги, порядок оплаты и
 * возврата, реквизиты продавца должны открываться по прямой ссылке без
 * входа в аккаунт. Цены приходят из config/billing.php — того же файла,
 * откуда их берёт кабинет, поэтому разойтись они не могут.
 */
defineProps<{
    legal: {
        name: string;
        status: string;
        inn: string;
        email: string;
        site: string;
        updated_at: string;
        storage_days: number;
    };
    packages: {
        key: string;
        title: string;
        credits: number;
        note: string;
        popular: boolean;
        price: string;
        perCredit: string;
    }[];
}>();
</script>

<template>
    <Layout title="Тарифы">
        <p>
            Оплата в «Слайдуше» — это пакет генераций. Одна генерация равна
            одной созданной презентации: сервис собирает структуру, тексты и
            оформление, отдаёт готовый PDF и открывает редактор, в котором
            слайды можно править и перепечатывать сколько угодно раз.
            Подписки нет, генерации не сгорают.
        </p>

        <div class="not-prose mt-8 grid gap-4 sm:grid-cols-3">
            <div
                v-for="pack in packages"
                :key="pack.key"
                class="flex flex-col rounded-[15px] border px-6 pt-6 pb-5"
                :class="
                    pack.popular
                        ? 'border-action bg-card shadow-[0_10px_30px_rgba(43,74,203,.1)]'
                        : 'border-rule bg-panel'
                "
            >
                <div class="flex min-h-[26px] items-center gap-2.5">
                    <span class="text-[19px] font-bold tracking-[-0.01em]">
                        {{ pack.title }}
                    </span>
                    <span
                        v-if="pack.popular"
                        class="bg-action-soft text-action-ink rounded-full px-2.5 py-1 text-[12.5px] font-bold"
                    >
                        чаще берут
                    </span>
                </div>

                <p class="mt-4 flex items-baseline gap-[7px]">
                    <span
                        class="text-[40px] leading-none font-bold tracking-[-0.03em] tabular-nums"
                    >
                        {{ pack.price }}
                    </span>
                    <span class="text-muted-foreground text-xl font-semibold">₽</span>
                </p>

                <p class="text-muted-foreground mt-3 mb-0 flex-1 text-[15px]">
                    {{ pack.credits }} генераций · {{ pack.perCredit }} ₽
                    за презентацию
                </p>
            </div>
        </div>

        <h2>Что входит в стоимость</h2>
        <ul>
            <li>Создание презентации по вашему тексту нейросетью.</li>
            <li>Выбор оформления: тема и цветовая гамма.</li>
            <li>
                Редактирование слайдов и повторная печать файла — без
                дополнительной платы, генерация списывается один раз.
            </li>
            <li>Готовый PDF и публичная ссылка на просмотр.</li>
            <li>Речь для выступающего отдельным PDF.</li>
        </ul>

        <h2>Оплата</h2>
        <p>
            Оплата банковской картой на сайте {{ legal.site }} через
            платёжного провайдера. Реквизиты карты вводятся на стороне
            провайдера и Исполнителю не передаются. Генерации зачисляются на
            счёт сразу после подтверждения платежа, чек приходит на вашу
            электронную почту.
        </p>
        <p>
            Цены указаны в рублях, окончательные. Первая генерация —
            бесплатно, чтобы проверить результат до оплаты.
        </p>

        <h2>Возврат</h2>
        <p>
            Если презентация не собралась по нашей вине, списанная генерация
            возвращается на счёт автоматически. Неиспользованные генерации
            можно вернуть деньгами — напишите на
            <a :href="`mailto:${legal.email}`">{{ legal.email }}</a>,
            возврат делается на ту же карту в срок до 10 рабочих дней.
        </p>

        <h2>Продавец</h2>
        <dl>
            <dt>Наименование</dt>
            <dd>{{ legal.name }}</dd>
            <dt>Статус</dt>
            <dd>{{ legal.status }}</dd>
            <dt>ИНН</dt>
            <dd>{{ legal.inn }}</dd>
            <dt>Почта</dt>
            <dd><a :href="`mailto:${legal.email}`">{{ legal.email }}</a></dd>
            <dt>Сайт</dt>
            <dd>{{ legal.site }}</dd>
        </dl>

        <p class="mt-8">
            Полные условия — в
            <Link href="/offer">публичной оферте</Link>. Порядок обработки
            данных описан в
            <Link href="/privacy">политике конфиденциальности</Link>.
        </p>
    </Layout>
</template>
