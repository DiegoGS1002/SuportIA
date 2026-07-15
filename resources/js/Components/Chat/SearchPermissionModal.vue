<template>
    <div class="flex items-center justify-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5 shrink-0 text-amber-500">
            <path
                d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.3 2.25h17.76A1.5 1.5 0 0 0 22.18 18L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>

        <p class="flex-1 text-sm text-amber-800">
            Nao encontramos esta solucao na base interna. Deseja autorizar uma pesquisa externa (OpenAI)?
        </p>

        <div class="flex shrink-0 gap-2">
            <button
                type="button"
                :disabled="disabled"
                class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50"
                @click="$emit('decline')"
            >
                Nao, encerrar
            </button>
            <button
                type="button"
                :disabled="disabled"
                class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                @click="$emit('authorize')"
            >
                Sim, pesquisar
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['authorize', 'decline']);
</script>

