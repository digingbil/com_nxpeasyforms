import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import './styles.css';

import SettingsApp from './components/SettingsApp.vue';

const bootstrap = () => {
    const builderEl = document.getElementById('nxp-easy-forms-builder');
    const settingsEl = document.getElementById('nxp-easy-forms-settings');

    if (builderEl) {
        const app = createApp(App);
        app.use(createPinia());
        app.mount(builderEl);
        return;
    }

    if (settingsEl) {
        const app = createApp(SettingsApp);
        app.mount(settingsEl);
        return;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}
