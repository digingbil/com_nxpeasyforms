<template>
    <div
        v-if="isOpen"
        class="nxp-template-modal-backdrop"
        @click="handleBackdropClick"
    >
        <div class="nxp-template-modal" role="dialog" aria-modal="true">
            <div class="nxp-template-modal__header">
                <h2>{{ __("Choose a Template", "nxp-easy-forms") }}</h2>
                <button
                    type="button"
                    class="nxp-template-modal__close"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    ×
                </button>
            </div>
            <div class="nxp-template-modal__content">
                <div class="nxp-template-grid">
                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        class="nxp-template-card"
                        @click="selectTemplate(template)"
                    >
                        <h3 class="nxp-template-card__title">
                            {{ template.name }}
                        </h3>
                        <p class="nxp-template-card__description">
                            {{ template.description }}
                        </p>
                        <div class="nxp-template-card__fields">
                            {{ template.fields.length }} fields
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { FORM_TEMPLATES } from "@/admin/constants/templates";
import { __ } from "@/utils/translate";

defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(["close", "select"]);

const templates = FORM_TEMPLATES;

function selectTemplate(template) {
    emit("select", template);
    emit("close");
}

function handleBackdropClick(event) {
    if (event.target === event.currentTarget) {
        emit("close");
    }
}
</script>

<style scoped>
.nxp-template-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
    padding: 20px;
}

.nxp-template-modal {
    background: var(--nxp-panel-bg);
    border-radius: 8px;
    box-shadow: 0 10px 40px var(--nxp-drawer-shadow);
    max-width: 800px;
    width: 100%;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.nxp-template-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--nxp-surface-border);
}

.nxp-template-modal__header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
}

.nxp-template-modal__close {
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: var(--nxp-muted-color);
}

.nxp-template-modal__close:hover {
    background: var(--bs-secondary-bg, rgba(0, 0, 0, 0.04));
    color: var(--bs-body-color);
}

.nxp-template-modal__content {
    padding: 24px;
    overflow-y: auto;
}

.nxp-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.nxp-template-card {
    background: var(--nxp-panel-bg);
    border: 2px solid var(--nxp-surface-border);
    border-radius: 6px;
    padding: 20px;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s;
}

.nxp-template-card:hover {
    border-color: var(--nxp-hover-border);
    box-shadow: 0 2px 8px var(--nxp-drawer-shadow);
    transform: translateY(-2px);
}

.nxp-template-card:focus {
    outline: 2px solid var(--nxp-hover-border);
    outline-offset: 2px;
}

.nxp-template-card__title {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--bs-body-color);
}

.nxp-template-card__description {
    margin: 0 0 12px 0;
    font-size: 15px;
    color: var(--nxp-muted-color);
    line-height: 1.5;
}

.nxp-template-card__fields {
    font-size: 13px;
    color: var(--nxp-muted-color);
    font-weight: 500;
}
</style>
