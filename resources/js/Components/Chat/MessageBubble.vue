<template>
    <div class="flex" :class="isUser ? 'justify-end' : 'justify-start'">
        <div class="flex max-w-[75%] items-end gap-2" :class="isUser ? 'flex-row-reverse' : 'flex-row'">
            <div
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                :class="isUser ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600'"
            >
                {{ isUser ? 'U' : 'IA' }}
            </div>

            <div
                class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed whitespace-pre-line"
                :class="
                    isUser
                        ? 'rounded-br-sm bg-bubble-user text-bubble-user-text'
                        : 'rounded-bl-sm bg-bubble-assistant text-navy-900'
                "
            >
                <p v-if="message.metadata?.has_image" class="mb-1 flex items-center gap-1 text-xs opacity-75">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5">
                        <path
                            d="M4 6.5A1.5 1.5 0 0 1 5.5 5h13A1.5 1.5 0 0 1 20 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5v-11Z"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <path d="m4 15 4-4 4 4 4-5 4 5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Print enviado
                </p>

                {{ message.content }}

                <p
                    v-if="sourceLabel"
                    class="mt-1.5 text-[11px] font-medium uppercase tracking-wider"
                    :class="isUser ? 'text-brand-700' : 'text-brand-600'"
                >
                    {{ sourceLabel }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
});

const isUser = computed(() => props.message.role === 'user');

const sourceLabel = computed(() => {
    switch (props.message.metadata?.source) {
        case 'internal_knowledge_base':
            return 'Base interna de solucoes';
        case 'external_search':
            return 'Pesquisa externa (OpenAI)';
        default:
            return null;
    }
});
</script>

