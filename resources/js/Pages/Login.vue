<template>
    <main class="flex min-h-screen w-full items-center justify-center bg-app-bg p-6">
        <form class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg" @submit.prevent="submit">
            <img
                src="/logo.png?v=20261006"
                alt="SuportIA"
                class="mx-auto mb-6 h-auto w-full max-w-[260px] object-contain"
            />
            <h1 class="mb-6 text-center text-xl font-semibold text-navy-900">
                {{ setupRequired ? 'Configurar administrador' : 'Acessar o SuportIA' }}
            </h1>

            <label v-if="setupRequired" class="mb-4 block text-sm font-medium text-slate-700">
                Nome
                <input v-model="name" type="text" autocomplete="name" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
            </label>

            <label class="mb-4 block text-sm font-medium text-slate-700">
                E-mail
                <input v-model="email" type="email" autocomplete="email" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
            </label>

            <label class="mb-5 block text-sm font-medium text-slate-700">
                Senha
                <input v-model="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
            </label>

            <label v-if="setupRequired" class="mb-5 block text-sm font-medium text-slate-700">
                Confirmar senha
                <input v-model="passwordConfirmation" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" />
            </label>

            <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

            <button type="submit" :disabled="loading" class="w-full rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white disabled:opacity-50">
                {{ loading ? 'Aguarde...' : setupRequired ? 'Criar administrador' : 'Entrar' }}
            </button>
        </form>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { authApi } from '../Services/api.js';

const router = useRouter();
const email = ref('');
const name = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const setupRequired = ref(false);

onMounted(async () => {
    const status = await authApi.setupStatus();
    setupRequired.value = status.required;
});

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        if (setupRequired.value) {
            await authApi.setup({
                name: name.value,
                email: email.value,
                password: password.value,
                password_confirmation: passwordConfirmation.value,
            });
        } else {
            await authApi.login({ email: email.value, password: password.value });
        }
        await router.push({ name: 'home' });
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Nao foi possivel entrar.';
    } finally {
        loading.value = false;
    }
}
</script>
