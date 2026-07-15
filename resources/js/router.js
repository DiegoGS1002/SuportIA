import { createRouter, createWebHistory } from 'vue-router';
import Home from './Pages/Home.vue';
import Chat from './Pages/Chat.vue';
import Documentos from './Pages/Documentos.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: Home,
        },
        {
            path: '/chat/:id?',
            name: 'chat',
            component: Chat,
            props: true,
        },
        {
            path: '/documentos',
            name: 'documentos',
            component: Documentos,
        },
    ],
});

export default router;

