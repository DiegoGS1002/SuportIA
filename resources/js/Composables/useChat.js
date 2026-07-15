import { computed, ref } from 'vue';
import { chatApi } from '../Services/api.js';

/**
 * Encapsulates the state and actions needed to drive a conversation with
 * the SupportAgent: creating/loading chats, sending messages/images and
 * handling the explicit consent flow for the external (Level 2) search.
 */
export function useChat() {
    const chats = ref([]);
    const currentChat = ref(null);
    const loading = ref(false);
    const sending = ref(false);
    const error = ref(null);
    const pendingImage = ref(null);

    const messages = computed(() => currentChat.value?.messages ?? []);

    const lastAssistantMessage = computed(() => {
        const list = messages.value;

        for (let i = list.length - 1; i >= 0; i--) {
            if (list[i].role === 'assistant') {
                return list[i];
            }
        }

        return null;
    });

    const awaitingExternalSearchConsent = computed(
        () => lastAssistantMessage.value?.metadata?.stage === 'awaiting_external_search_consent',
    );

    async function fetchChats() {
        loading.value = true;
        error.value = null;

        try {
            const response = await chatApi.list();
            chats.value = response.data ?? response;
        } catch (err) {
            error.value = 'Nao foi possivel carregar as conversas.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function startChat(software = null) {
        loading.value = true;
        error.value = null;

        try {
            const chat = await chatApi.create({ software });
            currentChat.value = { ...chat, messages: [] };

            return currentChat.value;
        } catch (err) {
            error.value = 'Nao foi possivel iniciar a conversa.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function loadChat(chatId) {
        loading.value = true;
        error.value = null;

        try {
            currentChat.value = await chatApi.get(chatId);

            return currentChat.value;
        } catch (err) {
            error.value = 'Nao foi possivel carregar a conversa.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function setPendingImage(file) {
        pendingImage.value = file;
    }

    function clearPendingImage() {
        pendingImage.value = null;
    }

    async function sendMessage(text, { externalSearchConsent = false, externalSearchDeclined = false } = {}) {
        if (!currentChat.value) {
            return null;
        }

        if (!text?.trim() && !pendingImage.value && !externalSearchConsent && !externalSearchDeclined) {
            return null;
        }

        sending.value = true;
        error.value = null;

        try {
            const result = await chatApi.sendMessage(currentChat.value.id, {
                message: text,
                image: pendingImage.value,
                externalSearchConsent,
                externalSearchDeclined,
            });

            currentChat.value = result.chat;
            clearPendingImage();

            return result;
        } catch (err) {
            error.value = 'Nao foi possivel enviar a mensagem. Tente novamente.';
            throw err;
        } finally {
            sending.value = false;
        }
    }

    function authorizeExternalSearch() {
        return sendMessage('', { externalSearchConsent: true });
    }

    function declineExternalSearch() {
        return sendMessage('', { externalSearchDeclined: true });
    }

    async function deleteChat(chatId) {
        try {
            await chatApi.delete(chatId);
            chats.value = chats.value.filter((c) => c.id !== chatId);

            if (currentChat.value?.id === chatId) {
                currentChat.value = null;
            }
        } catch (err) {
            error.value = 'Nao foi possivel excluir a conversa.';
            throw err;
        }
    }

    return {
        chats,
        currentChat,
        messages,
        loading,
        sending,
        error,
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
    };
}

