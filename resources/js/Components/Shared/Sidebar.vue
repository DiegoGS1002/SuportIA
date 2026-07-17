<template>
    <aside
        class="flex w-64 shrink-0 flex-col justify-between border-r border-white/5 bg-gradient-to-b from-[#071226] via-[#08152c] to-[#040b18] px-5 py-5 text-slate-300 shadow-[20px_0_50px_rgba(0,0,0,.35)]"
    >
        <div>
            <!-- Logo -->
            <router-link
                to="/"
                class="group mb-8 flex justify-center"
            >
                <img
                    src="/logo.png"
                    alt="Nexora Agent IA"
                    class="w-46 object-contain transition duration-300 group-hover:scale-100"
                />
            </router-link>

            <!-- Menu -->
            <nav class="mt-6 flex flex-col gap-3">
                <router-link
                    v-for="item in navItems"
                    :key="item.name"
                    :to="item.to"
                    class="group flex items-center gap-4 rounded-2xl px-4 py-4 transition-all duration-300"
                    :class="
                        isActive(item)
                            ? 'bg-[#182957] text-white shadow-lg'
                            : 'text-slate-300 hover:bg-[#121f43] hover:text-white'
                    "
                >
                    <component
                        :is="item.icon"
                        class="h-6 w-6 text-[#2D8CFF]"
                    />

                    <span class="text-lg font-medium">
                        {{ item.label }}
                    </span>
                </router-link>
            </nav>
        </div>

        <!-- Usuário -->
        <div
            class="group flex cursor-pointer items-center justify-between rounded-2xl px-3 py-3 transition hover:bg-[#111f42]"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 font-semibold text-white shadow-lg"
                >
                    DC
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">
                        Data C
                    </p>

                    <p class="text-xs text-slate-400">
                        Suporte Técnico
                    </p>
                </div>
            </div>

            <svg
                class="h-5 w-5 text-slate-400 transition group-hover:text-white"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        </div>
    </aside>
</template>

<script setup>
import { useRoute } from 'vue-router';
import { computed } from 'vue';
import HomeIcon from '../Icons/HomeIcon.vue';
import ChatIcon from '../Icons/ChatIcon.vue';
import DocumentsIcon from '../Icons/DocumentsIcon.vue';

const route = useRoute();

const navItems = [
    {
        name: 'home',
        label: 'Inicio',
        to: '/',
        icon: HomeIcon,
    },
    {
        name: 'chat',
        label: 'Chat',
        to: '/chat',
        icon: ChatIcon,
    },
    {
        name: 'documentos',
        label: 'Documentos',
        to: '/documentos',
        icon: DocumentsIcon,
    },
];

const isActive = (item) => {
    if (item.name === 'home') {
        return route.path === '/';
    }
    return route.path.startsWith('/' + item.name);
};
</script>


