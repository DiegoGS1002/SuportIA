<template>
    <div class="flex min-h-screen bg-app-bg text-navy-900">
        <Sidebar v-if="route.name !== 'login'" />

        <main class="flex flex-1 flex-col overflow-hidden">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import Sidebar from './Components/Shared/Sidebar.vue';
import { useRoute } from 'vue-router';

const route = useRoute();
</script>
