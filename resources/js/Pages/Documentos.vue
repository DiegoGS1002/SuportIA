<template>
    <div class="flex-1 overflow-y-auto">
        <div class="mx-auto max-w-5xl px-8 py-10">
            <!-- Tabs -->
            <div class="mb-6 flex gap-4 border-b border-slate-200">
                <button
                    type="button"
                    class="px-4 py-3 font-semibold transition"
                    :class="activeTab === 'knowledge' ? 'border-b-2 border-brand-600 text-brand-600' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'knowledge'"
                >
                    Base de Conhecimento
                </button>
                <button
                    type="button"
                    class="px-4 py-3 font-semibold transition"
                    :class="activeTab === 'exported' ? 'border-b-2 border-brand-600 text-brand-600' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'exported'"
                >
                    Documentos Exportados
                </button>
            </div>

            <!-- Knowledge Base Tab -->
            <div v-if="activeTab === 'knowledge'">
                <div class="mb-6 flex items-start justify-between">
                    <div class="flex flex-col gap-1">
                        <h1 class="text-2xl font-semibold text-navy-900">Base de conhecimento</h1>
                        <p class="text-sm text-slate-500">
                            Documentos indexados usados na busca interna (RAG - Nivel 1) antes de qualquer pesquisa externa.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700"
                        @click="showUploadModal = true"
                    >
                        + Adicionar documento
                    </button>
                </div>

            <!-- Filters -->
            <div class="mb-6 flex gap-2">
                <button
                    v-for="option in filters"
                    :key="option.value ?? 'all'"
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeFilter === option.value
                            ? 'border-brand-500 bg-brand-500 text-white'
                            : 'border-slate-200 text-slate-500 hover:bg-white'
                    "
                    @click="setFilter(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <p v-if="loading" class="rounded-2xl border border-slate-100 bg-white px-6 py-8 text-center text-sm text-slate-400">
                Carregando documentos...
            </p>

            <p
                v-else-if="documents.length === 0"
                class="rounded-2xl border border-slate-100 bg-white px-6 py-8 text-center text-sm text-slate-400"
            >
                Nenhum documento indexado ainda.
            </p>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <article
                    v-for="document in documents"
                    :key="document.id"
                    class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <span
                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-white"
                            :class="document.software === 'stella' ? 'bg-navy-700' : 'bg-brand-500'"
                        >
                            {{ document.software }}
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">{{ formatDate(document.created_at) }}</span>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                title="Excluir documento"
                                @click="confirmDelete(document)"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                                    <path
                                        d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <h2 class="mb-1 text-sm font-semibold text-navy-900">{{ document.title }}</h2>
                    <p class="line-clamp-3 text-sm text-slate-500">{{ document.content }}</p>
                </article>
            </div>
            </div>

            <!-- Exported Documents Tab -->
            <div v-if="activeTab === 'exported'">
                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-navy-900">Documentos Exportados</h1>
                    <p class="text-sm text-slate-500">
                        Conversas exportadas em PDF para consulta posterior.
                    </p>
                </div>

                <p v-if="loadingExported" class="rounded-2xl border border-slate-100 bg-white px-6 py-8 text-center text-sm text-slate-400">
                    Carregando documentos...
                </p>

                <p
                    v-else-if="exportedDocuments.length === 0"
                    class="rounded-2xl border border-slate-100 bg-white px-6 py-8 text-center text-sm text-slate-400"
                >
                    Nenhum documento exportado ainda.
                </p>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <article
                        v-for="document in exportedDocuments"
                        :key="document.id"
                        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <span class="rounded-full bg-green-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-green-700">
                                PDF
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">{{ formatDate(document.created_at) }}</span>
                                <button
                                    type="button"
                                    title="Baixar documento"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-blue-50 hover:text-blue-600"
                                    @click="downloadExportedDocument(document.id)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                                        <path
                                            d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                    title="Excluir documento"
                                    @click="confirmDeleteExported(document)"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                                        <path
                                            d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <h2 class="mb-1 text-sm font-semibold text-navy-900">{{ document.name }}</h2>
                        <p class="text-xs text-slate-500">{{ formatFileSize(document.file_size) }}</p>
                    </article>
                </div>
            </div>
        </div>

        <!-- Upload modal -->
        <div
            v-if="showUploadModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showUploadModal = false"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-semibold text-navy-900">Adicionar documento</h2>

                <form @submit.prevent="uploadDocument">
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Software</label>
                        <select
                            v-model="uploadForm.software"
                            required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                        >
                            <option value="">Selecione...</option>
                            <option value="home">Home</option>
                            <option value="stella">Stella</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Titulo</label>
                        <input
                            v-model="uploadForm.title"
                            type="text"
                            required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                            placeholder="Ex: Erro ao emitir NFe"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Arquivo (PDF, Word, TXT)</label>
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx,.txt"
                            required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                            @change="onFileSelect"
                        />
                        <p v-if="fileValidationError" class="mt-2 text-sm text-red-600">{{ fileValidationError }}</p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                            @click="showUploadModal = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="uploading"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-50"
                        >
                            {{ uploading ? 'Enviando...' : 'Adicionar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { knowledgeBaseApi, documentApi } from '../Services/api.js';

const documents = ref([]);
const exportedDocuments = ref([]);
const loading = ref(false);
const loadingExported = ref(false);
const activeTab = ref('knowledge');
const activeFilter = ref(null);
const showUploadModal = ref(false);
const uploading = ref(false);

const uploadForm = reactive({
    software: '',
    title: '',
    file: null,
});

const fileValidationError = ref('');

const filters = [
    { label: 'Todos', value: null },
    { label: 'Home', value: 'home' },
    { label: 'Stella', value: 'stella' },
];

const ALLOWED_TYPES = {
    'application/pdf': '.pdf',
    'application/msword': '.doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx',
    'text/plain': '.txt',
};

const MAX_FILE_SIZE = 10 * 1024 * 1024;

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(new Date(value));
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function validateFile(file) {
    fileValidationError.value = '';

    if (!file) {
        fileValidationError.value = 'Selecione um arquivo.';
        return false;
    }

    if (file.size > MAX_FILE_SIZE) {
        fileValidationError.value = `Arquivo muito grande. Máximo: ${formatFileSize(MAX_FILE_SIZE)}`;
        return false;
    }

    if (!Object.keys(ALLOWED_TYPES).includes(file.type)) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['pdf', 'doc', 'docx', 'txt'].includes(ext)) {
            fileValidationError.value = 'Formato inválido. Aceitos: PDF, DOC, DOCX, TXT';
            return false;
        }
    }

    if (file.name.length > 255) {
        fileValidationError.value = 'Nome do arquivo muito longo.';
        return false;
    }

    return true;
}

async function loadKnowledgeBase() {
    loading.value = true;

    try {
        const response = await knowledgeBaseApi.list({ software: activeFilter.value });
        documents.value = response.data ?? response;
    } finally {
        loading.value = false;
    }
}

async function loadExportedDocuments() {
    loadingExported.value = true;

    try {
        const response = await documentApi.list();
        exportedDocuments.value = Array.isArray(response) ? response : response.data ?? response;
    } finally {
        loadingExported.value = false;
    }
}

function setFilter(value) {
    activeFilter.value = value;
    loadKnowledgeBase();
}

function onFileSelect(event) {
    const file = event.target.files?.[0] ?? null;
    if (validateFile(file)) {
        uploadForm.file = file;
    } else {
        uploadForm.file = null;
        event.target.value = '';
    }
}

async function uploadDocument() {
    if (!uploadForm.software || !uploadForm.title || !uploadForm.file) {
        fileValidationError.value = 'Preencha todos os campos.';
        return;
    }

    if (!validateFile(uploadForm.file)) {
        return;
    }

    uploading.value = true;

    try {
        await knowledgeBaseApi.create({
            software: uploadForm.software,
            title: uploadForm.title,
            file: uploadForm.file,
        });

        showUploadModal.value = false;
        uploadForm.software = '';
        uploadForm.title = '';
        uploadForm.file = null;
        fileValidationError.value = '';
        await loadKnowledgeBase();
    } catch (err) {
        const errorMsg = err.response?.data?.error || err.response?.data?.message || 'Erro ao adicionar documento.';
        fileValidationError.value = errorMsg;
    } finally {
        uploading.value = false;
    }
}

async function confirmDelete(document) {
    if (confirm(`Tem certeza que deseja excluir "${document.title}"? Esta acao nao pode ser desfeita.`)) {
        try {
            await knowledgeBaseApi.delete(document.id);
            await loadKnowledgeBase();
        } catch (err) {
            alert('Erro ao excluir documento.');
        }
    }
}

async function downloadExportedDocument(documentId) {
    try {
        const response = await documentApi.download(documentId);
        const url = window.URL.createObjectURL(response.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `documento-${documentId}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (err) {
        alert('Erro ao baixar documento.');
    }
}

async function confirmDeleteExported(document) {
    if (confirm(`Tem certeza que deseja excluir "${document.name}"? Esta acao nao pode ser desfeita.`)) {
        try {
            await documentApi.delete(document.id);
            await loadExportedDocuments();
        } catch (err) {
            alert('Erro ao excluir documento.');
        }
    }
}

async function init() {
    await loadKnowledgeBase();
    await loadExportedDocuments();
}

onMounted(init);
</script>

