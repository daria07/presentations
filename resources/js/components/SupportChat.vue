<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, Send, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

/*
   Чат с администратором: кнопка в углу и окошко с перепиской.

   Пока окошко открыто, переписка опрашивается раз в несколько секунд —
   как статус презентации. Закрыто — не опрашивается: о новом ответе
   скажет точка на кнопке (её число приходит с каждой страницей) и
   письмо на почту.
*/

type Message = {
    id: number;
    fromAdmin: boolean;
    body: string;
    createdAt: string | null;
};

const POLL_INTERVAL = 5000;
const MAX = 2000;

const page = usePage();

/* В админке кнопка не нужна: там свой раздел с диалогами */
const visible = computed(
    () => Boolean(page.props.auth?.user) && !page.url.startsWith('/admin'),
);

const open = ref(false);
const messages = ref<Message[]>([]);
const loaded = ref(false);
const body = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const list = ref<HTMLElement | null>(null);

/* Непрочитанные ответы: с сервера с каждой страницей, а при открытии
   окошка сразу ноль — сервер в этот момент отмечает их прочитанными */
const unread = ref(page.props.support?.unread ?? 0);
watch(
    () => page.props.support?.unread,
    (value) => {
        if (!open.value) unread.value = value ?? 0;
    },
);

const lastId = computed(() => messages.value.at(-1)?.id ?? 0);
const left = computed(() => MAX - body.value.length);
const canSend = computed(
    () => body.value.trim() !== '' && left.value >= 0 && !sending.value,
);

let timer: number | undefined;

function xsrf(): string {
    // XSRF-токен Laravel кладёт в cookie; в заголовок — раскодированным
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

async function scrollDown() {
    await nextTick();
    list.value?.scrollTo({ top: list.value.scrollHeight });
}

async function load() {
    try {
        const response = await fetch(`/support?after=${lastId.value}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) return;

        const data: { messages: Message[] } = await response.json();
        const known = new Set(messages.value.map((m) => m.id));
        const fresh = data.messages.filter((m) => !known.has(m.id));

        loaded.value = true;

        if (fresh.length) {
            messages.value.push(...fresh);
            scrollDown();
        }
    } catch {
        // Сеть моргнула — спросим на следующем круге
    }
}

function show() {
    open.value = true;
    unread.value = 0;
    load();
    timer = window.setInterval(load, POLL_INTERVAL);
}

function hide() {
    open.value = false;
    window.clearInterval(timer);
}

async function send() {
    if (!canSend.value) return;

    sending.value = true;
    error.value = null;

    try {
        const response = await fetch('/support', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body: body.value }),
        });

        if (response.status === 429) {
            error.value = 'Слишком часто. Подождите минуту и отправьте снова.';
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            error.value =
                data?.errors?.body?.[0] ??
                data?.message ??
                'Не получилось отправить. Попробуйте ещё раз.';
            return;
        }

        if (!messages.value.some((m) => m.id === data.message.id)) {
            messages.value.push(data.message);
        }

        body.value = '';
        scrollDown();
    } catch {
        error.value = 'Нет связи. Проверьте интернет и отправьте снова.';
    } finally {
        sending.value = false;
    }
}

/* Enter — отправить, Shift+Enter — новая строка, как в мессенджерах */
function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        send();
    }
}

function time(iso: string | null): string {
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

/* Ссылка из письма «Вам ответили» ведёт сюда с ?support=open */
onMounted(() => {
    if (new URLSearchParams(window.location.search).get('support') === 'open') {
        show();
    }
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <template v-if="visible">
        <button
            v-if="!open"
            type="button"
            class="bg-foreground text-background fixed right-4 bottom-4 z-40 flex cursor-pointer items-center gap-2 rounded-full px-4 py-3 text-sm font-medium shadow-[0_4px_16px_rgba(21,22,26,.22)] transition-transform hover:scale-[1.03] sm:right-6 sm:bottom-6"
            aria-label="Написать нам"
            @click="show"
        >
            <MessageCircle class="size-5" />
            <span class="hidden sm:inline">Написать нам</span>
            <span
                v-if="unread > 0"
                class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-red-500 text-[11px] font-semibold text-white"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div
            v-else
            class="bg-background border-border fixed inset-0 z-50 flex flex-col sm:inset-auto sm:right-6 sm:bottom-6 sm:h-[520px] sm:max-h-[calc(100vh-3rem)] sm:w-[380px] sm:rounded-2xl sm:border sm:shadow-[0_12px_40px_rgba(21,22,26,.18)]"
            role="dialog"
            aria-label="Чат с поддержкой"
        >
            <div
                class="border-border flex items-center justify-between border-b px-4 py-3"
            >
                <div>
                    <p class="font-semibold">Напишите нам</p>
                    <p class="text-muted-foreground text-xs">
                        Ответим здесь и продублируем на почту
                    </p>
                </div>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5"
                    aria-label="Закрыть"
                    @click="hide"
                >
                    <X class="size-5" />
                </button>
            </div>

            <div ref="list" class="flex-1 space-y-2 overflow-y-auto px-4 py-4">
                <p
                    v-if="loaded && !messages.length"
                    class="text-muted-foreground py-8 text-center text-sm leading-relaxed"
                >
                    Вопрос, ошибка, идея — пишите.<br />
                    Обычно отвечаем в течение дня.
                </p>

                <div
                    v-for="m in messages"
                    :key="m.id"
                    class="flex"
                    :class="m.fromAdmin ? 'justify-start' : 'justify-end'"
                >
                    <div
                        class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm"
                        :class="
                            m.fromAdmin
                                ? 'bg-muted rounded-bl-md'
                                : 'bg-foreground text-background rounded-br-md'
                        "
                    >
                        <p class="break-words whitespace-pre-line">
                            {{ m.body }}
                        </p>
                        <p
                            class="mt-0.5 text-right text-[11px] tabular-nums opacity-60"
                        >
                            {{ time(m.createdAt) }}
                        </p>
                    </div>
                </div>
            </div>

            <form class="border-border border-t p-3" @submit.prevent="send">
                <p v-if="error" class="mb-2 text-xs text-red-600">
                    {{ error }}
                </p>
                <div class="flex items-end gap-2">
                    <textarea
                        v-model="body"
                        rows="2"
                        :maxlength="MAX"
                        placeholder="Сообщение…"
                        class="border-border bg-background focus:ring-ring max-h-32 min-h-[44px] min-w-0 flex-1 resize-none rounded-xl border px-3 py-2 text-base outline-none focus:ring-2 sm:text-sm"
                        @keydown="onKeydown"
                    />
                    <button
                        type="submit"
                        class="bg-foreground text-background flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl transition-opacity disabled:cursor-default disabled:opacity-40"
                        :disabled="!canSend"
                        aria-label="Отправить"
                    >
                        <Send class="size-4" />
                    </button>
                </div>
            </form>
        </div>
    </template>
</template>
