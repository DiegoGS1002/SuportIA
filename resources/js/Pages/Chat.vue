<template>
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- Header -->
        <header class="flex items-center justify-between border-b border-slate-100 bg-white px-8 py-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold text-white"
                    :class="currentChat?.software === 'stella' ? 'bg-navy-700' : 'bg-brand-500'"
                >
                    {{ currentChat?.software === 'stella' ? 'ST' : currentChat?.software === 'home' ? 'HM' : 'IA' }}
                </div>
                <div>
                    <h1 class="text-sm font-semibold text-navy-900">
                        {{ currentChat ? currentChat.title || 'Atendimento em andamento' : 'Selecione uma conversa' }}
                    </h1>
                    <p class="text-xs text-slate-400">
                        {{ currentChat ? softwareLabel(currentChat.software) : 'Ou inicie uma nova pelo Home' }}
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <button
                    v-if="currentChat"
                    type="button"
                    title="Ações"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                    @click="showActionsModal = true"
                >
                    ⋮
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-brand-200 px-3 py-1.5 text-xs font-semibold text-brand-600 transition hover:bg-brand-50"
                    @click="newChat('home')"
                >
                    + Home
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-brand-200 px-3 py-1.5 text-xs font-semibold text-brand-600 transition hover:bg-brand-50"
                    @click="newChat('stella')"
                >
                    + Stella
                </button>
            </div>
        </header>

        <!-- Chat Actions Modal -->
        <ChatActionsModal
            :is-open="showActionsModal"
            @close="showActionsModal = false"
            @export-pdf="handleExportPdf"
            @delete="handleDeleteChat"
        />

        <div class="flex flex-1 overflow-hidden">
            <!-- Conversations list -->
            <aside class="hidden w-72 shrink-0 overflow-y-auto border-r border-slate-100 bg-white lg:block">
                <ul class="divide-y divide-slate-100">
                    <li v-for="chat in chats" :key="chat.id">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-5 py-3.5 text-left transition"
                            :class="chat.id === currentChat?.id ? 'bg-app-bg' : 'hover:bg-app-bg/60'"
                            @click="openChat(chat.id)"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold text-white"
                                :class="chat.software === 'stella' ? 'bg-navy-700' : 'bg-brand-500'"
                            >
                                {{ chat.software === 'stella' ? 'ST' : 'HM' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-navy-900">
                                    {{ chat.title || 'Atendimento sem titulo' }}
                                </p>
                                <p class="truncate text-xs text-slate-400">{{ softwareLabel(chat.software) }}</p>
                            </div>
                        </button>
                    </li>
                </ul>
            </aside>

            <!-- Chat window -->
            <div class="flex-1 overflow-hidden">
                <ChatWindow
                    v-if="currentChat"
                    :messages="messages"
                    :sending="sending"
                    :pending-image="pendingImage"
                    :awaiting-external-search-consent="awaitingExternalSearchConsent"
                    @send="onSend"
                    @select-image="setPendingImage"
                    @remove-image="clearPendingImage"
                    @authorize-search="authorizeExternalSearch"
                    @decline-search="declineExternalSearch"
                />

                <div v-else class="flex h-full flex-col items-center justify-center gap-3 text-center text-slate-400">
                    <p class="text-sm">Nenhuma conversa selecionada.</p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-700"
                            @click="newChat('home')"
                        >
                            Iniciar atendimento Home
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-navy-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-navy-600"
                            @click="newChat('stella')"
                        >
                            Iniciar atendimento Stella
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ChatWindow from '../Components/Chat/ChatWindow.vue';
import ChatActionsModal from '../Components/Chat/ChatActionsModal.vue';
import { useChat } from '../Composables/useChat.js';

const props = defineProps({
    id: {
        type: [String, Number],
        default: null,
    },
});

const route = useRoute();
const router = useRouter();
const showActionsModal = ref(false);

const {
    chats,
    currentChat,
    messages,
    sending,
    pendingImage,
    awaitingExternalSearchConsent,
    fetchChats,
    startChat,
    loadChat,
    setPendingImage,
    clearPendingImage,
    sendMessage,
    authorizeExternalSearch,
    declineExternalSearch,
    deleteChat,
    exportChatToPdf,
} = useChat();

function softwareLabel(software) {
    if (software === 'stella') return 'Stella';
    if (software === 'home') return 'Home';
    return 'Geral';
}

async function openChat(chatId) {
    await router.push({ name: 'chat', params: { id: chatId } });
}

async function newChat(software) {
    const chat = await startChat(software);
    await router.push({ name: 'chat', params: { id: chat.id } });
    await fetchChats();
}

async function onSend(text) {
    await sendMessage(text);
}

async function handleDeleteChat() {
    if (!currentChat.value) return;

    const chatId = currentChat.value.id;
    await deleteChat(chatId);
    await router.push({ name: 'chat' });
}

async function handleExportPdf(documentName) {
    if (!currentChat.value) return;

    try {
        await exportChatToPdf(currentChat.value.id, documentName);
        alert('Documento exportado com sucesso! Verifique na página Documentos.');
    } catch (error) {
        alert('Erro ao exportar documento: ' + error.message);
    }
}

async function syncFromRoute() {
    await fetchChats();

    const chatId = props.id ?? route.params.id;

    if (chatId) {
        await loadChat(chatId);
    }
}

watch(() => route.params.id, syncFromRoute);
onMounted(syncFromRoute);
</script>

