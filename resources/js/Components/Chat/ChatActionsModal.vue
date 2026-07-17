<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-navy-900">
                    Ações do Atendimento
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Escolha uma ação para continuar
                </p>
            </div>

            <!-- Export to PDF -->
            <div class="mb-4">
                <label class="mb-2 block text-sm font-medium text-navy-900">
                    Exportar como PDF
                </label>
                <div class="flex gap-2">
                    <input
                        v-model="documentName"
                        type="text"
                        placeholder="Nome do documento"
                        class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                    />
                    <button
                        type="button"
                        :disabled="!documentName.trim() || exporting"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleExportPdf"
                    >
                        <span v-if="!exporting">Exportar</span>
                        <span v-else>Exportando...</span>
                    </button>
                </div>
            </div>

            <!-- Delete Chat -->
            <div class="mb-6 border-t border-slate-200 pt-4">
                <button
                    type="button"
                    :disabled="deleting"
                    class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="handleDelete"
                >
                    <span v-if="!deleting">Excluir Atendimento</span>
                    <span v-else>Excluindo...</span>
                </button>
            </div>

            <!-- Close -->
            <button
                type="button"
                class="w-full rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-navy-900 transition hover:bg-slate-200"
                @click="close"
            >
                Cancelar
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['close', 'export-pdf', 'delete']);

const documentName = ref('');
const exporting = ref(false);
const deleting = ref(false);

function close() {
    documentName.value = '';
    emit('close');
}

async function handleExportPdf() {
    if (!documentName.value.trim()) return;

    exporting.value = true;
    try {
        await emit('export-pdf', documentName.value.trim());
        documentName.value = '';
        close();
    } finally {
        exporting.value = false;
    }
}

async function handleDelete() {
    if (!confirm('Tem certeza que deseja excluir esta conversa? Esta ação não pode ser desfeita.')) {
        return;
    }

    deleting.value = true;
    try {
        await emit('delete');
        close();
    } finally {
        deleting.value = false;
    }
}
</script>

