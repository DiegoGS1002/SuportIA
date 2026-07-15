<template>
    <div class="flex-1 overflow-y-auto">
        <div class="mx-auto max-w-5xl px-8 py-10">
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
import { knowledgeBaseApi } from '../Services/api.js';

const documents = ref([]);
const loading = ref(false);
const activeFilter = ref(null);
const showUploadModal = ref(false);
const uploading = ref(false);

const uploadForm = reactive({
    software: '',
    title: '',
    file: null,
});

const filters = [
    { label: 'Todos', value: null },
    { label: 'Home', value: 'home' },
    { label: 'Stella', value: 'stella' },
];

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(new Date(value));
}

async function load() {
    loading.value = true;

    try {
        const response = await knowledgeBaseApi.list({ software: activeFilter.value });
        documents.value = response.data ?? response;
    } finally {
        loading.value = false;
    }
}

function setFilter(value) {
    activeFilter.value = value;
    load();
}

function onFileSelect(event) {
    uploadForm.file = event.target.files?.[0] ?? null;
}

async function uploadDocument() {
    if (!uploadForm.software || !uploadForm.title || !uploadForm.file) {
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
        await load();
    } catch (err) {
        alert('Erro ao adicionar documento. Verifique se o arquivo e valido.');
    } finally {
        uploading.value = false;
    }
}

async function confirmDelete(document) {
    if (confirm(`Tem certeza que deseja excluir "${document.title}"? Esta acao nao pode ser desfeita.`)) {
        try {
            await knowledgeBaseApi.delete(document.id);
            await load();
        } catch (err) {
            alert('Erro ao excluir documento.');
        }
    }
}

onMounted(load);
</script>

