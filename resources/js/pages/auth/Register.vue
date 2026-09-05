<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Регистрация',
        description: 'Заполните поля, чтобы создать аккаунт',
    },
});
</script>

<template>
    <Head title="Регистрация" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Имя</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Как к вам обращаться"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Почта</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Пароль</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Пароль"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Повторите пароль</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Повторите пароль"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Создать аккаунт
            </Button>

            <!-- Согласие фиксируется самим фактом регистрации: отдельная
                 галочка ничего не добавляет юридически, но добавляет шаг -->
            <p class="text-muted-foreground text-center text-xs leading-relaxed">
                Создавая аккаунт, вы принимаете
                <a href="/offer" target="_blank" class="underline underline-offset-2">условия оферты</a>
                и даёте согласие на обработку персональных данных на условиях
                <a href="/privacy" target="_blank" class="underline underline-offset-2">политики конфиденциальности</a>.
            </p>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Уже есть аккаунт?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Войти</TextLink
            >
        </div>
    </Form>
</template>
