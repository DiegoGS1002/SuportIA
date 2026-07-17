import { ref } from 'vue';
import { documentApi } from '../Services/api.js';

export function useDocuments() {
    const documents = ref([]);
    const loading = ref(false);
    const error = ref(null);

    async function fetchDocuments() {
        loading.value = true;
        error.value = null;

        try {
            const response = await documentApi.list();
            documents.value = Array.isArray(response) ? response : response.data ?? response;
        } catch (err) {
            error.value = 'Não foi possível carregar os documentos.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function downloadDocument(documentId) {
        try {
            const blob = await documentApi.download(documentId);
            const url = window.URL.createObjectURL(blob.data || blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `documento-${documentId}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (err) {
            error.value = 'Não foi possível baixar o documento.';
            throw err;
        }
    }

    async function deleteDocument(documentId) {
        try {
            await documentApi.delete(documentId);
            documents.value = documents.value.filter((d) => d.id !== documentId);
        } catch (err) {
            error.value = 'Não foi possível excluir o documento.';
            throw err;
        }
    }

    return {
        documents,
        loading,
        error,
        fetchDocuments,
        downloadDocument,
        deleteDocument,
    };
}

