<template>
    <div class="flex items-center gap-3 rounded-xl border border-brand-100 bg-brand-50 px-3 py-2">
        <img :src="previewUrl" alt="Pre-visualizacao do print" class="h-12 w-12 rounded-lg object-cover" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-navy-900">{{ file.name }}</p>
            <p class="text-xs text-slate-500">{{ sizeLabel }}</p>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-full p-1.5 text-slate-400 transition hover:bg-white hover:text-red-500"
            title="Remover imagem"
            @click="$emit('remove')"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    file: {
        type: File,
        required: true,
    },
});

defineEmits(['remove']);

const previewUrl = ref('');

function buildPreview() {
    revokePreview();
    previewUrl.value = URL.createObjectURL(props.file);
}

function revokePreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
}

const sizeLabel = computed(() => `${(props.file.size / 1024).toFixed(0)} KB`);

watch(() => props.file, buildPreview, { immediate: true });
onBeforeUnmount(revokePreview);
</script>

