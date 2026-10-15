<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Send } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';

type Conversation = {
    userId: number;
    email: string | null;
    name: string | null;
    unread: number;
    preview: string;
    fromAdmin: boolean;
    at: string | null;
};

type Message = {
    id: number;
    fromAdmin: boolean;
    body: string;
    createdAt: string | null;
};

const props = defineProps<{
    conversations: {
        items: Conversation[];
        currentPage: number;
        lastPage: number;
        prevUrl: string | null;
        nextUrl: string | null;
    };
    thread: {
        user: {
            id: number;
            name: string;
            email: string;
            credits: number;
            presentations: number;
            payments: number;
            paidTotal: number;
            createdAt: string | null;
        };
        messages: Message[];
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Админка', href: '/admin' },
            { title: 'Чат', href: '/admin/support' },
        ],
    },
});

/* Раз в 10 секунд подтягиваем новые сообщения: и в открытом диалоге,
   и в списке, и число в меню. Остальная страница не трогается */
const POLL_INTERVAL = 10000;
let timer: number | undefined;

onMounted(() => {
    timer = window.setInterval(() => {
        if (document.hidden) return;

        // reload прокрутку не трогает — диалог не прыгает
        router.reload({ only: ['conversations', 'thread', 'support'] });
    }, POLL_INTERVAL);
    scrollDown();
});

onBeforeUnmount(() => window.clearInterval(timer));

const list = ref<HTMLElement | null>(null);

async function scrollDown() {
    await nextTick();
    list.value?.scrollTo({ top: list.value.scrollHeight });
}

/* Прокручиваем вниз, только когда появилось новое сообщение,
   а не на каждом опросе: иначе нельзя почитать историю */
watch(
    () => [props.thread?.user.id, props.thread?.messages.at(-1)?.id],
    (now, before) => {
        if (now[0] !== before?.[0] || now[1] !== before?.[1]) scrollDown();
    },
);

const form = useForm({ body: '' });

/* Написать первым: находим человека по почте и открываем пустой диалог */
const finder = useForm({ email: '' });

function find() {
    if (!finder.email.trim() || finder.processing) return;

    finder.get('/admin/support/find', { preserveScroll: true });
}

function send() {
    if (!props.thread || !form.body.trim() || form.processing) return;

    form.post(`/admin/support/${props.thread.user.id}`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        send();
    }
}

const rubles = (kopecks: number) =>
    (kopecks / 100).toLocaleString('ru-RU', { maximumFractionDigits: 0 });

function when(iso: string | null): string {
    if (!iso) return '';

    const date = new Date(iso);
    const today = new Date().toDateString() === date.toDateString();

    return date.toLocaleString(
        'ru-RU',
        today
            ? { hour: '2-digit', minute: '2-digit' }
            : {
                  day: 'numeric',
                  month: 'short',
                  hour: '2-digit',
                  minute: '2-digit',
              },
    );
}
</script>

<template>
    <Head title="Чат — админка" />

    <div class="flex w-full flex-col gap-4 px-4 py-6 sm:px-6">
        <PageHeader title="Чат с пользователями" />

        <div
            class="border-border bg-card grid min-h-[560px] overflow-hidden rounded-xl border lg:grid-cols-[320px_1fr]"
        >
            <!-- Диалоги. На телефоне скрыты, когда открыт диалог -->
            <div
                class="border-border flex min-w-0 flex-col lg:border-r"
                :class="thread ? 'hidden lg:flex' : 'flex'"
            >
                <form class="border-border border-b p-3" @submit.prevent="find">
                    <div class="flex gap-2">
                        <input
                            v-model="finder.email"
                            type="email"
                            placeholder="Написать по почте…"
                            class="border-border bg-background focus:ring-ring min-w-0 flex-1 rounded-lg border px-3 py-1.5 text-sm outline-none focus:ring-2"
                        />
                        <button
                            type="submit"
                            class="bg-foreground text-background shrink-0 cursor-pointer rounded-lg px-3 py-1.5 text-sm font-medium transition-opacity disabled:cursor-default disabled:opacity-40"
                            :disabled="
                                !finder.email.trim() || finder.processing
                            "
                        >
                            Открыть
                        </button>
                    </div>
                    <p
                        v-if="finder.errors.email"
                        class="mt-1.5 text-xs text-red-600"
                    >
                        {{ finder.errors.email }}
                    </p>
                </form>

                <div
                    v-if="!conversations.items.length"
                    class="text-muted-foreground p-6 text-sm"
                >
                    Пока никто не писал.
                </div>

                <div class="divide-border flex-1 divide-y overflow-y-auto">
                    <Link
                        v-for="c in conversations.items"
                        :key="c.userId"
                        :href="`/admin/support/${c.userId}`"
                        preserve-scroll
                        class="block cursor-pointer px-4 py-3 transition-colors"
                        :class="
                            thread?.user.id === c.userId
                                ? 'bg-secondary'
                                : 'hover:bg-secondary/60'
                        "
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <p
                                class="truncate text-sm"
                                :class="c.unread ? 'font-semibold' : ''"
                            >
                                {{ c.email ?? 'пользователь удалён' }}
                            </p>
                            <span
                                class="text-muted-foreground shrink-0 text-xs tabular-nums"
                            >
                                {{ when(c.at) }}
                            </span>
                        </div>
                        <div
                            class="mt-0.5 flex items-center justify-between gap-2"
                        >
                            <p class="text-muted-foreground truncate text-xs">
                                <span v-if="c.fromAdmin">Вы: </span
                                >{{ c.preview }}
                            </p>
                            <span
                                v-if="c.unread"
                                class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-red-500 px-1.5 text-[11px] font-semibold text-white"
                            >
                                {{ c.unread }}
                            </span>
                        </div>
                    </Link>
                </div>

                <div
                    v-if="conversations.lastPage > 1"
                    class="border-border flex items-center justify-between border-t px-4 py-2 text-xs"
                >
                    <Link
                        v-if="conversations.prevUrl"
                        :href="conversations.prevUrl"
                        :only="['conversations']"
                        preserve-scroll
                        preserve-state
                        class="cursor-pointer hover:underline"
                        >Назад</Link
                    >
                    <span v-else />
                    <span class="text-muted-foreground tabular-nums">
                        {{ conversations.currentPage }} из
                        {{ conversations.lastPage }}
                    </span>
                    <Link
                        v-if="conversations.nextUrl"
                        :href="conversations.nextUrl"
                        :only="['conversations']"
                        preserve-scroll
                        preserve-state
                        class="cursor-pointer hover:underline"
                        >Вперёд</Link
                    >
                    <span v-else />
                </div>
            </div>

            <!-- Диалог -->
            <div v-if="thread" class="flex min-w-0 flex-col">
                <div
                    class="border-border flex flex-wrap items-center gap-x-4 gap-y-1 border-b px-4 py-3"
                >
                    <Link
                        href="/admin/support"
                        class="text-muted-foreground hover:text-foreground -ml-1 cursor-pointer rounded p-1 lg:hidden"
                        aria-label="К диалогам"
                    >
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div class="min-w-0">
                        <Link
                            :href="`/admin/users/${thread.user.id}`"
                            class="block truncate font-medium hover:underline"
                        >
                            {{ thread.user.email }}
                        </Link>
                        <p class="text-muted-foreground text-xs">
                            {{ thread.user.name }} · презентаций
                            {{ thread.user.presentations }} · генераций на счету
                            {{ thread.user.credits }} ·
                            <template v-if="thread.user.payments">
                                оплат {{ thread.user.payments }} на
                                {{ rubles(thread.user.paidTotal) }} ₽
                            </template>
                            <template v-else>не платил</template>
                        </p>
                    </div>
                </div>

                <div
                    ref="list"
                    class="max-h-[60vh] flex-1 space-y-2 overflow-y-auto px-4 py-4"
                >
                    <p
                        v-if="!thread.messages.length"
                        class="text-muted-foreground py-8 text-center text-sm leading-relaxed"
                    >
                        Сообщений пока нет. Напишите первым — человек увидит
                        сообщение в чате на сайте и получит письмо.
                    </p>
                    <div
                        v-for="m in thread.messages"
                        :key="m.id"
                        class="flex"
                        :class="m.fromAdmin ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm"
                            :class="
                                m.fromAdmin
                                    ? 'bg-foreground text-background rounded-br-md'
                                    : 'bg-muted rounded-bl-md'
                            "
                        >
                            <p class="break-words whitespace-pre-line">
                                {{ m.body }}
                            </p>
                            <p
                                class="mt-0.5 text-right text-[11px] tabular-nums opacity-60"
                            >
                                {{ when(m.createdAt) }}
                            </p>
                        </div>
                    </div>
                </div>

                <form class="border-border border-t p-3" @submit.prevent="send">
                    <p
                        v-if="form.errors.body"
                        class="mb-2 text-xs text-red-600"
                    >
                        {{ form.errors.body }}
                    </p>
                    <div class="flex items-end gap-2">
                        <textarea
                            v-model="form.body"
                            rows="2"
                            maxlength="2000"
                            placeholder="Ответ… Enter — отправить, Shift+Enter — новая строка"
                            class="border-border bg-background focus:ring-ring max-h-40 min-h-[44px] flex-1 resize-y rounded-xl border px-3 py-2 text-sm outline-none focus:ring-2"
                            @keydown="onKeydown"
                        />
                        <button
                            type="submit"
                            class="bg-foreground text-background flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl transition-opacity disabled:cursor-default disabled:opacity-40"
                            :disabled="!form.body.trim() || form.processing"
                            aria-label="Отправить"
                        >
                            <Send class="size-4" />
                        </button>
                    </div>
                    <p class="text-muted-foreground mt-1.5 text-xs">
                        Человек получит ответ в чате на сайте и письмом на
                        почту.
                    </p>
                </form>
            </div>

            <div
                v-else
                class="text-muted-foreground hidden items-center justify-center p-6 text-sm lg:flex"
            >
                Выберите диалог слева
            </div>
        </div>
    </div>
</template>
