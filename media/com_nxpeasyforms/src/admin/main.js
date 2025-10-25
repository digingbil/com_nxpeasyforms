import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import './styles.css';

const bootstrap = () => {
    const builderEl = document.getElementById('nxp-easy-forms-builder');

    if (builderEl) {
        const app = createApp(App);
        app.use(createPinia());
        app.mount(builderEl);
        return;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}
