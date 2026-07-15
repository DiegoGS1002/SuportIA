<template>
    <div class="flex-1 overflow-y-auto bg-slate-50">
        <div class="mx-auto max-w-7xl px-8 py-10">

            <!-- HERO -->
            <section
                class="relative overflow-hidden rounded-[32px] border border-slate-200 bg-white px-12 py-16 shadow-xl">

                <!-- Glow -->
                <div
                    class="absolute left-1/2 top-0 h-[320px] w-[720px] -translate-x-1/2 rounded-full bg-brand-300/20 blur-[120px]">
                </div>

                <div class="relative z-10 flex flex-col items-center">

                    <!-- Logo highlight backdrop -->
                    <div class="relative flex items-center justify-center">
                        <div
                            class="absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-br from-brand-200/40 via-brand-300/20 to-transparent blur-2xl">
                        </div>
                        <img src="/logo.png" alt="Nexora"
                             class="relative z-10 h-80 w-auto object-contain drop-shadow-[0_25px_50px_rgba(37,99,235,.35)]" />
                    </div>

                    <h1 class="mt-10 text-center text-5xl font-bold tracking-tight text-navy-900">
                        Seu agente de
                        <span class="text-brand-500">
                           IA
                        </span>
                    </h1>

                    <p class="mt-5 max-w-2xl text-center text-lg leading-8 text-slate-500">
                        Converse, envie documentos, faça perguntas e obtenha
                        respostas utilizando o Nexora Agent IA.
                    </p>

                    <!-- FEATURES -->

                    <div class="mt-14 grid w-full grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">

                        <div v-for="feature in features" :key="feature.title"
                             class="group rounded-3xl border border-slate-100 bg-white p-8 shadow-md transition duration-300 hover:-translate-y-1 hover:shadow-xl">

                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl"
                                 :class="feature.iconBg">

                                <component :is="feature.icon" class="h-8 w-8" :class="feature.iconColor" />

                            </div>

                            <h3 class="mt-6 text-center text-base font-semibold text-navy-900">
                                {{ feature.title }}
                            </h3>

                            <p class="mt-3 text-center text-sm leading-6 text-slate-500">
                                {{ feature.description }}
                            </p>

                        </div>

                    </div>

                </div>

            </section>

            <!-- SOFTWARE -->

            <section class="mt-16">

                <div class="mb-8">

                    <h2 class="text-3xl font-bold text-navy-900">
                        Como podemos ajudar hoje?
                    </h2>

                    <p class="mt-2 text-base text-slate-500">
                        Escolha um software para iniciar um novo atendimento.
                    </p>

                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                    <button v-for="option in softwareOptions" :key="option.value" :disabled="starting"
                            @click="startNewChat(option.value)"
                            class="group rounded-3xl border border-slate-200 bg-white p-7 text-left shadow-md transition duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60">

                        <div class="flex items-center">

                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl text-2xl font-bold text-white"
                                 :class="option.color">

                                {{ option.initials }}

                            </div>

                            <div class="ml-5 flex-1">

                                <h3 class="text-xl font-semibold text-navy-900">
                                    {{ option.label }}
                                </h3>

                                <p class="mt-1 text-sm text-slate-500">
                                    {{ option.description }}
                                </p>

                            </div>

                            <svg class="h-6 w-6 text-brand-500 transition group-hover:translate-x-1"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                                <path d="M9 6l6 6-6 6" stroke-linecap="round"
                                      stroke-linejoin="round" />

                            </svg>

                        </div>

                    </button>

                </div>

            </section>
        </div>
    </div>
</template>

<script setup>
import { h, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useChat } from '../Composables/useChat.js';

const router = useRouter();

const {
    chats,
    loading,
    fetchChats,
    startChat,
} = useChat();

const starting = ref(false);

/* -------------------------------------------------------------------------- */
/*                                    Icons                                   */
/* -------------------------------------------------------------------------- */

const ChatBubbleIcon = () =>
    h(
        'svg',
        {
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': '1.8',
        },
        [
            h('path', {
                d: 'M4 5.5h16v10H9.5L5 19v-3.5H4v-10Z',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
        ]
    );

const DocumentIcon = () =>
    h(
        'svg',
        {
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': '1.8',
        },
        [
            h('path', {
                d: 'M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
            h('path', {
                d: 'M14 3.5V8h4',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
            h('path', {
                d: 'M9 13h6',
                'stroke-linecap': 'round',
            }),
            h('path', {
                d: 'M9 16.5h6',
                'stroke-linecap': 'round',
            }),
        ]
    );

const BrainIcon = () =>
    h(
        'svg',
        {
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': '1.8',
        },
        [
            h('path', {
                d: 'M9 4.5a2.5 2.5 0 0 0-2.5 2.5 2.5 2.5 0 0 0-2 4A2.5 2.5 0 0 0 6 15.5v1a2.5 2.5 0 0 0 2.5 2.5h.5v-13Z',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
            h('path', {
                d: 'M15 4.5a2.5 2.5 0 0 1 2.5 2.5 2.5 2.5 0 0 1 2 4 2.5 2.5 0 0 1-2 2.5v1A2.5 2.5 0 0 1 15 17v-1',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
            h('path', {
                d: 'M9 4.5h6v13H9z',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
        ]
    );

const ShieldIcon = () =>
    h(
        'svg',
        {
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': '1.8',
        },
        [
            h('path', {
                d: 'M12 3.5 5 6v5c0 4.5 3 7.7 7 9 4-1.3 7-4.5 7-9V6l-7-2.5Z',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
            h('path', {
                d: 'M9 12.2 11.2 14 15 10',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            }),
        ]
    );

/* -------------------------------------------------------------------------- */
/*                                  Features                                  */
/* -------------------------------------------------------------------------- */

const features = [
    {
        title: 'Respostas inteligentes',
        description:
            'Obtenha respostas claras e precisas para qualquer dúvida.',
        icon: ChatBubbleIcon,
        iconBg: 'bg-brand-50',
        iconColor: 'text-brand-500',
    },
    {
        title: 'Análise de documentos',
        description:
            'Envie documentos e receba análises instantâneas.',
        icon: DocumentIcon,
        iconBg: 'bg-blue-50',
        iconColor: 'text-blue-500',
    },
    {
        title: 'IA em evolução',
        description:
            'O agente aprende continuamente durante os atendimentos.',
        icon: BrainIcon,
        iconBg: 'bg-purple-50',
        iconColor: 'text-purple-500',
    },
    {
        title: 'Segurança',
        description:
            'Seus dados protegidos seguindo boas práticas de segurança.',
        icon: ShieldIcon,
        iconBg: 'bg-emerald-50',
        iconColor: 'text-emerald-500',
    },
];
</script>
