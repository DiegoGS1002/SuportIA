import axios from 'axios';

/**
 * Pre-configured axios instance for the SuportIA JSON API.
 */
const http = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
    },
});

export const chatApi = {
    /**
     * Lists the existing conversations.
     */
    list() {
        return http.get('/chats').then((response) => response.data);
    },

    /**
     * Starts a new conversation for the given software (home|stella).
     */
    create({ software = null, title = null } = {}) {
        return http.post('/chats', { software, title }).then((response) => response.data);
    },

    /**
     * Fetches a single conversation with its full message history.
     */
    get(chatId) {
        return http.get(`/chats/${chatId}`).then((response) => response.data);
    },

    /**
     * Sends a message (text and/or image) to the SupportAgent.
     *
     * @param {number|string} chatId
     * @param {{ message?: string, image?: File|null, externalSearchConsent?: boolean, externalSearchDeclined?: boolean }} payload
     */
    sendMessage(chatId, { message = '', image = null, externalSearchConsent = false, externalSearchDeclined = false } = {}) {
        const formData = new FormData();

        if (message) {
            formData.append('message', message);
        }

        if (image) {
            formData.append('image', image);
        }

        if (externalSearchConsent) {
            formData.append('external_search_consent', '1');
        }

        if (externalSearchDeclined) {
            formData.append('external_search_declined', '1');
        }

        return http
            .post(`/chats/${chatId}/messages`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            .then((response) => response.data);
    },
};

export const knowledgeBaseApi = {
    /**
     * Lists the indexed knowledge base documents (RAG - Level 1).
     */
    list({ software = null } = {}) {
        return http.get('/knowledge-bases', { params: { software } }).then((response) => response.data);
    },

    /**
     * Uploads and indexes a document (PDF, Word, TXT).
     */
    create({ software, title, file }) {
        const formData = new FormData();
        formData.append('software', software);
        formData.append('title', title);
        formData.append('file', file);

        return http
            .post('/knowledge-bases', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            .then((response) => response.data);
    },

    /**
     * Deletes a knowledge base document.
     */
    delete(documentId) {
        return http.delete(`/knowledge-bases/${documentId}`).then((response) => response.data);
    },
};

export default http;

