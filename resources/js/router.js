import { createRouter, createWebHistory } from 'vue-router';
import Home from './Pages/Home.vue';
import Chat from './Pages/Chat.vue';
import Documentos from './Pages/Documentos.vue';
import Login from './Pages/Login.vue';
import { authApi } from './Services/api.js';

let authenticated = null;

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: Login,
            meta: { public: true },
        },
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

router.beforeEach(async (to) => {
    if (to.meta.public) {
        return true;
    }

    // A failed check must not be cached permanently: the login request can
    // establish a valid session without reloading the SPA.
    if (authenticated !== true) {
        try {
            await authApi.user();
            authenticated = true;
        } catch {
            authenticated = false;
        }
    }

    return authenticated ? true : { name: 'login' };
});

export default router;
