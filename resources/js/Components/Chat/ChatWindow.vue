<template>
    <div class="flex h-full flex-col">
        <!-- Messages -->
        <div ref="scrollArea" class="flex-1 space-y-4 overflow-y-auto px-8 py-6">
            <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center text-center text-slate-400">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-linear-to-br from-cyan-400 to-brand-600 text-white">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-7 w-7">
                        <path
                            d="M4 5.5h16v10H9.5L5 19v-3.5H4v-10Z"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <p class="text-sm">Descreva o problema ou envie um print para comecar.</p>
            </div>

            <MessageBubble v-for="message in messages" :key="message.id" :message="message" />

            <div v-if="sending" class="flex items-center gap-2 text-xs text-slate-400">
                <span class="flex h-2 w-2 animate-pulse rounded-full bg-brand-400"></span>
                O agente esta digitando...
            </div>
        </div>

        <!-- Consent banner (Level 2 external search) -->
        <div v-if="awaitingExternalSearchConsent" class="px-8 pb-2">
            <SearchPermissionModal
                :disabled="sending"
                @authorize="$emit('authorize-search')"
                @decline="$emit('decline-search')"
            />
        </div>

        <!-- Composer -->
        <div class="border-t border-slate-100 bg-header-bg px-8 py-4">
            <ImagePreview v-if="pendingImage" class="mb-3" :file="pendingImage" @remove="$emit('remove-image')" />
            <p v-if="validationError || error" class="mb-3 text-sm text-red-600">{{ validationError || error }}</p>

            <form class="flex items-end gap-3" @submit.prevent="submit">
                <button
                    type="button"
                    title="Anexar print"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-app-bg"
                    @click="$refs.fileInput.click()"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                        <path
                            d="M8 12.5 12.5 8a3 3 0 0 1 4.24 4.24l-6 6a5 5 0 0 1-7.07-7.07l6.5-6.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>
                <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onFileChange" />

                <textarea
                    v-model="draft"
                    rows="1"
                    placeholder="Descreva o erro ou a mensagem exibida na tela..."
                    class="max-h-32 flex-1 resize-none rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-navy-900 placeholder:text-slate-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                    @keydown.enter.exact.prevent="submit"
                ></textarea>

                <button
                    type="submit"
                    :disabled="sending || (!draft.trim() && !pendingImage)"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                        <path d="M4.5 12h15m0 0-6-6m6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import MessageBubble from './MessageBubble.vue';
import SearchPermissionModal from './SearchPermissionModal.vue';
import ImagePreview from '../Shared/ImagePreview.vue';

const props = defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
    sending: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    pendingImage: {
        type: File,
        default: null,
    },
    awaitingExternalSearchConsent: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send', 'select-image', 'remove-image', 'authorize-search', 'decline-search']);

const draft = ref('');
const scrollArea = ref(null);
const validationError = ref('');
const pendingClientMessageId = ref(null);

const ALLOWED_IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

function onFileChange(event) {
    const file = event.target.files?.[0];

    if (file) {
        validationError.value = '';

        if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
            validationError.value = 'Formato invalido. Envie PNG, JPEG ou WebP.';
        } else if (file.size > MAX_IMAGE_SIZE) {
            validationError.value = 'A imagem deve ter no maximo 10 MB.';
        } else {
            emit('select-image', file);
        }
    }

    event.target.value = '';
}

function submit() {
    if (props.sending || (!draft.value.trim() && !props.pendingImage)) {
        return;
    }

    pendingClientMessageId.value ??= crypto.randomUUID();
    emit('send', {
        text: draft.value.trim(),
        clientMessageId: pendingClientMessageId.value,
        onSuccess: () => {
            draft.value = '';
            pendingClientMessageId.value = null;
            validationError.value = '';
        },
        onError: () => {
            validationError.value = 'Falha no envio. Sua mensagem foi preservada; tente novamente.';
        },
    });
}

function scrollToBottom() {
    nextTick(() => {
        if (scrollArea.value) {
            scrollArea.value.scrollTop = scrollArea.value.scrollHeight;
        }
    });
}

watch(() => props.messages.length, scrollToBottom);
</script>
