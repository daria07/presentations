<script setup lang="ts">
import { Form, Head } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Field from "@/components/Field.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Обратная связь", href: "/feedback" }],
    },
});

const MAX = 5000;

const body = ref("");

const left = computed(() => MAX - body.value.length);

/* Те же десять знаков, что требует сервер: отказывать уже после
   отправки — значит потратить время человека впустую */
const canSend = computed(
    () => body.value.trim().length >= 10 && left.value >= 0,
);
</script>

<template>
    <Head title="Обратная связь" />

    <div class="mx-auto w-full max-w-2xl px-4 py-8">
        <PageHeader title="Обратная связь" />

        <p class="text-muted-foreground mb-6 leading-relaxed">
            Напишите, что вам понравилось или что не понравилось. Мы обязательно
            во всём разберёмся и учтём ваши пожелания. Заранее спасибо!
        </p>

        <Form
            method="post"
            action="/feedback"
            :options="{ preserveScroll: true }"
            :reset-on-success="['body']"
            v-slot="{ errors, processing }"
            class="space-y-6"
            @success="body = ''"
        >
            <Field
                :error="errors.body"
                :counter="left < 500 ? String(left) : undefined"
                for="body"
            >
                <Textarea
                    id="body"
                    name="body"
                    v-model="body"
                    rows="8"
                    :maxlength="MAX"
                    autofocus
                    placeholder="Что понравилось, что нет, чего не хватает"
                    class="resize-none"
                />
            </Field>

            <Button type="submit" :disabled="!canSend || processing">
                {{ processing ? "Отправляем…" : "Отправить" }}
            </Button>
        </Form>
    </div>
</template>
