<template>
    <div>
        <div class="nxp-builder" v-if="!store.loading">
            <!-- Quick Start Tip - Only for new forms -->
            <div v-if="showNewFormTip" class="nxp-quick-start">
                <div class="nxp-quick-start__icon">
                    <span class="dashicons dashicons-lightbulb"></span>
                </div>
                <div class="nxp-quick-start__content">
                    <strong>{{ __("Quick Start:", "nxp-easy-forms") }}</strong>
                    {{ __("Save time by applying a template! Click the", "nxp-easy-forms") }}
                    <img
                        class="nxp-icon nxp-icon--inline"
                        :src="ICON_HEX_PLUS"
                        alt=""
                        aria-hidden="true"
                    />
                    {{ __("button below to choose from pre-built forms.", "nxp-easy-forms") }}
                </div>
                <button
                    type="button"
                    class="nxp-quick-start__dismiss"
                    @click="dismissNewFormTip()"
                    :title="__('Dismiss')"
                >
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <header class="nxp-builder__top">
                <div class="nxp-builder__title">
                    <label for="nxp-form-title">{{ __("Form title") }}</label>
                    <input
                        id="nxp-form-title"
                        type="text"
                        :placeholder="__('Untitled form')"
                        v-model="formTitle"
                    />
                </div>
                <div class="nxp-builder__actions">
                    <button
                        type="button"
                        class="button button-secondary nxp-builder__template"
                        @click="toggleTemplates(true)"
                        :title="__('Apply template', 'nxp-easy-forms')"
                    >
                        <img class="nxp-icon" :src="ICON_HEX_PLUS" alt="" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="button button-secondary nxp-builder__preview"
                        @click="togglePreview(true)"
                        :title="__('Preview form', 'nxp-easy-forms')"
                    >
                        <span class="dashicons dashicons-visibility"></span>
                        {{ __("Preview", "nxp-easy-forms") }}
                    </button>
                    <button
                        type="button"
                        class="button button-primary nxp-builder__save"
                        :disabled="store.saving"
                        @click="store.saveForm()"
                    >
                        <span v-if="store.saving">{{ __("Saving…") }}</span>
                        <span v-else>{{ __("Save form") }}</span>
                    </button>
                </div>
            </header>

            <div v-if="store.error" class="notice notice-error">
                <p>{{ store.error }}</p>
            </div>

            <div v-if="store.notice" class="notice notice-success">
                <p>{{ store.notice }}</p>
                <button
                    type="button"
                    class="notice-dismiss"
                    @click="store.clearNotice()"
                >
                    <span class="screen-reader-text">{{ __("Dismiss") }}</span>
                </button>
            </div>

        <main class="nxp-builder__layout" ref="layoutRef">
            <FieldPalette @add-field="addField" />
            <FormCanvas
                :fields="store.fields"
                :selected-id="selectedFieldId"
                @select="selectField"
                @remove="removeField"
                @duplicate="duplicateField"
                @reorder="handleReorder"
                @create-field="handleCreateFromPalette"
                @open-settings="toggleSettings(true)"
            />
        </main>

        <FieldEditorDrawer
            :field="selectedField"
            :fields="store.fields"
            @update="updateField"
            @close="selectedFieldId = null"
        />

        <FormSettingsModal
            :visible="showSettings"
            :options="store.options"
            :fields="store.fields"
            @update="updateOptions"
            @close="toggleSettings(false)"
            @test-email="sendTestEmail"
        />

            <TemplateModal
                :is-open="showTemplates"
                @close="toggleTemplates(false)"
                @select="applyTemplate"
            />

            <FormPreviewModal
                :visible="showPreview"
                :form-title="store.title"
                :fields="store.fields"
                @close="togglePreview(false)"
            />
        </div>
        <div v-else class="nxp-builder__loading">
            {{ __("Loading form…") }}
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from "vue";
import { useFormStore } from "@/admin/stores/formStore";
import FieldPalette from "@/admin/components/FieldPalette.vue";
import FormCanvas from "@/admin/components/FormCanvas.vue";
import FieldEditorDrawer from "@/admin/components/FieldEditorDrawer.vue";
import FormSettingsModal from "@/admin/components/FormSettingsModal.vue";
import TemplateModal from "@/admin/components/TemplateModal.vue";
import FormPreviewModal from "@/admin/components/FormPreviewModal.vue";
import { __ } from "@/utils/i18n";
import ICON_HEX_PLUS from "../../assets/icons/hexagon-plus.svg";

const store = useFormStore();
const selectedFieldId = ref(null);
const showSettings = ref(false);
const showTemplates = ref(false);
const showPreview = ref(false);
const showNewFormTip = ref(false);
const layoutRef = ref(null);

const formTitle = computed({
    get: () => store.title,
    set: (value) => {
        store.title = value;
        store.hasUnsavedChanges = true;
    },
});

const selectedField = computed(
    () =>
        store.fields.find((field) => field.id === selectedFieldId.value) ||
        null,
);

const handleBeforeUnload = (event) => {
    if (store.hasUnsavedChanges) {
        event.preventDefault();
        event.returnValue = __(
            "You have unsaved changes. If you leave now, your changes will be lost.",
            "nxp-easy-forms"
        );
        return event.returnValue;
    }
};

onMounted(() => {
    store.bootstrap();
    window.addEventListener("beforeunload", handleBeforeUnload);

    // Show tip for new forms only
    if (store.formId === 0) {
        showNewFormTip.value = true;
    }

    const updateBottomSafe = () => {
        try {
            const bodyContent = document.getElementById('wpbody-content');
            const footer = document.getElementById('wpfooter');
            const paddingBottom = bodyContent ? parseInt(getComputedStyle(bodyContent).paddingBottom || '0', 10) : 0;
            const footerHeight = footer ? footer.offsetHeight : 0;
            const safe = Math.max(paddingBottom, footerHeight) + 12; // add small buffer
            if (layoutRef.value && Number.isFinite(safe)) {
                layoutRef.value.style.setProperty('--nxp-bottom-safe', `${safe}px`);
            }
        } catch (e) {
            // noop
        }
    };
    updateBottomSafe();
    window.addEventListener('resize', updateBottomSafe);
    // Also observe footer size changes if available
    if (window.ResizeObserver) {
        const ro = new ResizeObserver(updateBottomSafe);
        const footerEl = document.getElementById('wpfooter');
        if (footerEl) ro.observe(footerEl);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener("beforeunload", handleBeforeUnload);
});

const dismissNewFormTip = () => {
    showNewFormTip.value = false;
};

watch(
    () => store.fields.length,
    () => {
        if (!selectedField.value) {
            selectedFieldId.value = null;
        }
    },
);

watch(
    () => store.formId,
    (newId) => {
        if (newId) {
            showNewFormTip.value = false;
        }
    },
);

const addField = (type) => {
    store.addField(type);
};

const handleCreateFromPalette = ({ type, index }) => {
    store.addField(type, index);
};

const selectField = (id) => {
    selectedFieldId.value = id;
};

const removeField = (id) => {
    store.removeField(id);
    if (selectedFieldId.value === id) {
        selectedFieldId.value = null;
    }
};

const duplicateField = (id) => {
    store.duplicateField(id);
};

const handleReorder = ({ oldIndex, newIndex }) => {
    store.updateFieldOrder(oldIndex, newIndex);
};

const updateField = (payload) => {
    store.updateField(payload.id, payload);
};

const updateOptions = (options) => {
    store.updateOptions(options);
    store.updateThrottle(options.throttle);
};

const toggleSettings = (value) => {
    showSettings.value = value;
};

const toggleTemplates = (value) => {
    showTemplates.value = value;
};

const togglePreview = (value) => {
    showPreview.value = value;
};

const applyTemplate = (template) => {
    store.applyTemplate(template);
};

const sendTestEmail = (payload) => {
  store.sendTestEmail({ ...payload, formId: store.formId });
};
</script>

<style scoped>
.nxp-builder {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.nxp-builder__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
}

.nxp-builder__title {
    flex: 1;
}

.nxp-builder__title label {
    display: block;
    font-weight: 600;
    font-size: 1.05rem;
    margin-bottom: 6px;
}

.nxp-builder__title input {
    width: 100%;
    font-size: 1.1rem;
    padding: 8px 10px;
}

.nxp-builder__actions {
    display: flex;
    gap: 10px;
}

.nxp-builder__actions button.nxp-builder__template {
    line-height: 1.3;
}

.nxp-builder__preview {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
}

.nxp-builder__preview .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.nxp-builder__actions .nxp-builder__template .nxp-icon {
    width: 22px;
    height: 22px;
}

.nxp-icon {
    width: 18px;
    height: 18px;
    vertical-align: middle;
}

.nxp-icon--inline {
    width: 16px;
    height: 16px;
}

.nxp-builder__save {
    min-height: 44px;
    border: 1px solid transparent;
}

.nxp-builder__layout {
    display: grid;
    grid-template-columns: minmax(240px, 260px) 1fr;
    gap: 20px;
}

/* Ensure the main canvas column can scroll within the constrained layout */
.nxp-builder__layout .nxp-panel {
    /* Constrain height so side panels (like the field palette) can scroll internally */
    min-height: 520px;
    /* Tweak these offsets if WP admin bars/toolbars change
       Subtract the WP admin footer safe area (~65px) to avoid bottom cut-off */
    max-height: calc(100vh - 180px - 65px);
    overflow: hidden;
}

.nxp-builder__loading {
    padding: 60px;
    text-align: center;
    font-size: 1.2rem;
}

.notice {
    position: relative;
}

/* Quick Start notification */
.nxp-quick-start {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    background: #e7f5fe;
    border-left: 4px solid #2271b1;
    border-radius: 4px;
    margin-bottom: 20px;
    position: relative;
}

.nxp-quick-start__icon {
    flex-shrink: 0;
    color: #2271b1;
    font-size: 20px;
    line-height: 1;
    margin-top: 2px;
}

.nxp-quick-start__icon .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}

.nxp-quick-start__content {
    flex: 1;
    line-height: 1.6;
    color: #1d2327;
}

.nxp-quick-start__content strong {
    color: #1d2327;
    font-weight: 600;
}

.nxp-quick-start__dismiss {
    flex-shrink: 0;
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    color: #50575e;
    line-height: 1;
    transition: color 0.15s ease;
    margin-top: 2px;
}

.nxp-quick-start__dismiss:hover {
    color: #d63638;
}

.nxp-quick-start__dismiss .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}
</style>
