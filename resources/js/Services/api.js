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

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    http.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

export const authApi = {
    setupStatus() {
        return http.get('/setup/status').then((response) => response.data);
    },
    setup(payload) {
        return http.post('/setup', payload).then((response) => response.data);
    },
    login(credentials) {
        return http.post('/login', credentials).then((response) => response.data);
    },
    user() {
        return http.get('/auth/user').then((response) => response.data);
    },
    logout() {
        return http.post('/logout').then((response) => response.data);
    },
};

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
    sendMessage(chatId, { message = '', image = null, externalSearchConsent = false, externalSearchDeclined = false, clientMessageId = null } = {}) {
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

        if (clientMessageId) {
            formData.append('client_message_id', clientMessageId);
        }

        return http
            .post(`/chats/${chatId}/messages`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            .then((response) => response.data);
    },

    /**
     * Deletes a chat.
     */
    delete(chatId) {
        return http.delete(`/chats/${chatId}`).then((response) => response.data);
    },

    /**
     * Exports a chat to PDF and stores it as a document.
     */
    exportPdf(chatId, documentName) {
        return http.post(`/chats/${chatId}/export-pdf`, { name: documentName }).then((response) => response.data);
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
    retry(documentId) {
        return http.post(`/knowledge-bases/${documentId}/retry`).then((response) => response.data);
    },
};

export const documentApi = {
    /**
     * Lists all exported documents.
     */
    list() {
        return http.get('/documents').then((response) => response.data);
    },

    /**
     * Downloads a document.
     */
    download(documentId) {
        return http.get(`/documents/${documentId}/download`, { responseType: 'blob' });
    },

    /**
     * Deletes a document.
     */
    delete(documentId) {
        return http.delete(`/documents/${documentId}`).then((response) => response.data);
    },
};

export default http;
