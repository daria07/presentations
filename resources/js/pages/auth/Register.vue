<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import SocialLogin from '@/components/SocialLogin.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
    /* Текст согласия на рекламные письма — с сервера, вместе с версией */
    marketingText: string;
    /* Настроен ли вход через VK ID и Google */
    vkId: boolean;
    google: boolean;
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

    <SocialLogin
        v-if="vkId || google"
        :vk="vkId"
        :google="google"
        consent
        class="mb-6"
    />

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

            <!-- Согласие на рекламные письма: отдельное и добровольное.
                 Галочка снята по умолчанию — заранее поставленную ФАС
                 может не признать согласием (ст. 18 закона «О рекламе»).
                 Без неё регистрироваться можно -->
            <Label
                for="marketing"
                class="text-muted-foreground flex items-start gap-3 text-sm leading-snug font-normal"
            >
                <Checkbox
                    id="marketing"
                    name="marketing"
                    value="1"
                    :tabindex="5"
                    class="mt-0.5"
                />
                <span>{{ marketingText }}</span>
            </Label>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="6"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Создать аккаунт
            </Button>

            <!-- Согласие фиксируется самим фактом регистрации: отдельная
                 галочка ничего не добавляет юридически, но добавляет шаг -->
            <p
                class="text-muted-foreground text-center text-xs leading-relaxed"
            >
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
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Уже есть аккаунт?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >Войти</TextLink
            >
        </div>
    </Form>
</template>
