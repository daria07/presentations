<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/* VK не отдал почту: она нужна для чеков и писем о готовности */
defineProps<{
    name: string;
}>();

defineOptions({
    layout: {
        title: 'Последний шаг',
        description:
            'Укажите почту — на неё придут чеки и письма о готовых презентациях',
    },
});
</script>

<template>
    <Head title="Почта для аккаунта" />

    <Form
        action="/auth/vk/email"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <p v-if="name" class="text-center text-sm">
            {{ name }}, VK не передал нам вашу почту.
        </p>

        <div class="grid gap-2">
            <Label for="email">Почта</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />
            <InputError :message="errors.email" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Создать аккаунт
        </Button>

        <p class="text-muted-foreground text-center text-xs leading-relaxed">
            Создавая аккаунт, вы принимаете
            <a
                href="/offer"
                target="_blank"
                class="underline underline-offset-2"
                >условия оферты</a
            >
            и даёте
            <a
                href="/consent"
                target="_blank"
                class="underline underline-offset-2"
                >согласие на обработку персональных данных</a
            >
            на условиях
            <a
                href="/privacy"
                target="_blank"
                class="underline underline-offset-2"
                >политики конфиденциальности</a
            >.
        </p>
    </Form>
</template>
