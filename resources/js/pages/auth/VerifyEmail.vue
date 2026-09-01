<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Подтверждение почты',
        description:
            'Мы отправили письмо со ссылкой — перейдите по ней, чтобы подтвердить адрес.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Подтверждение почты" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Новое письмо со ссылкой отправлено на указанный при регистрации адрес.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Отправить письмо ещё раз
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Выйти
        </TextLink>
    </Form>
</template>
