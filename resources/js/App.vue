<template>
    <div class="flex min-h-screen bg-app-bg text-navy-900">
        <Sidebar />

        <main class="flex flex-1 flex-col overflow-hidden">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import Sidebar from './Components/Shared/Sidebar.vue';
</script>

