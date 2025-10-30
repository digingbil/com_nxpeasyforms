<template>
    <div>
        <div class="nxp-builder" v-if="!store.loading">
            <!-- Quick Start Tip - Only for new forms -->
            <div v-if="showNewFormTip" class="nxp-quick-start">
                <div class="nxp-quick-start__icon">
                    <span class="fa-regular fa-lightbulb" aria-hidden="true"></span>
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
                    <span class="fa-solid fa-xmark" aria-hidden="true"></span>
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
                <div class="nxp-builder__alias">
                    <label for="nxp-form-alias">
                        {{ __("COM_NXPEASYFORMS_FIELD_ALIAS_LABEL") }}
                        <span class="nxp-builder__alias-hint">
                            {{ __("COM_NXPEASYFORMS_FIELD_ALIAS_HINT") }}
                        </span>
                    </label>
                    <input
                        id="nxp-form-alias"
                        type="text"
                        :placeholder="__('COM_NXPEASYFORMS_FIELD_ALIAS_PLACEHOLDER')"
                        v-model="formAlias"
                    />
                </div>
                <div class="nxp-builder__actions">
                    <button
                        type="button"
                        class="btn btn-outline-secondary nxp-builder__template"
                        @click="toggleTemplates(true)"
                        :title="__('Apply template', 'nxp-easy-forms')"
                    >
                        <img class="nxp-icon" :src="ICON_HEX_PLUS" alt="" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary nxp-builder__preview"
                        @click="togglePreview(true)"
                        :title="__('Preview form', 'nxp-easy-forms')"
                    >
                        <span class="fa-regular fa-eye" aria-hidden="true"></span>
                        {{ __("Preview", "nxp-easy-forms") }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary nxp-builder__save"
                        :disabled="store.saving"
                        @click="store.saveForm()"
                    >
                        <span v-if="store.saving">{{ __("Saving…") }}</span>
                        <span v-else>{{ __("Save form") }}</span>
                    </button>
                </div>
            </header>

            <div
                v-if="store.error"
                class="alert alert-danger nxp-alert"
                role="alert"
            >
                <span class="fa-solid fa-triangle-exclamation nxp-alert__icon" aria-hidden="true"></span>
                <p class="nxp-alert__message">{{ store.error }}</p>
                <button
                    type="button"
                    class="nxp-alert__dismiss"
                    @click="store.clearError()"
                    :title="__('Dismiss')"
                >
                    <span class="fa-solid fa-xmark" aria-hidden="true"></span>
                    <span class="screen-reader-text">{{ __("Dismiss") }}</span>
                </button>
            </div>

            <div
                v-if="store.notice"
                class="alert alert-success nxp-alert"
                role="alert"
            >
                <span class="fa-solid fa-circle-check nxp-alert__icon" aria-hidden="true"></span>
                <p class="nxp-alert__message">{{ store.notice }}</p>
                <button
                    type="button"
                    class="nxp-alert__dismiss"
                    @click="store.clearNotice()"
                    :title="__('Dismiss')"
                >
                    <span class="fa-solid fa-xmark" aria-hidden="true"></span>
                    <span class="screen-reader-text">{{ __("Dismiss") }}</span>
                </button>
            </div>

        <main class="nxp-builder__layout" ref="layoutRef">
            <FieldPalette @add-field="addField" />
            <FormCanvas
                :fields="store.fields"
                :selected-id="selectedFieldId"
                :integration-tags="enabledIntegrationTags"
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
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useFormStore } from "@/admin/stores/formStore";
import FieldPalette from "@/admin/components/FieldPalette.vue";
import FormCanvas from "@/admin/components/FormCanvas.vue";
import FieldEditorDrawer from "@/admin/components/FieldEditorDrawer.vue";
import FormSettingsModal from "@/admin/components/FormSettingsModal.vue";
import TemplateModal from "@/admin/components/TemplateModal.vue";
import FormPreviewModal from "@/admin/components/FormPreviewModal.vue";
import { __ } from "@/utils/translate";
import ICON_HEX_PLUS from "../../assets/icons/hexagon-plus.svg";

const store = useFormStore();
const selectedFieldId = ref(null);
const showSettings = ref(false);
const showTemplates = ref(false);
const showPreview = ref(false);
const showNewFormTip = ref(false);
const layoutRef = ref(null);
const hiddenFieldInputs = {
    title: null,
    alias: null,
    fields: null,
    settings: null,
};

const { options, formId } = storeToRefs(store);

const integrationTagDefinitions = [
    { key: "zapier", label: __("Zapier", "nxp-easy-forms") },
    { key: "make", label: __("Make", "nxp-easy-forms") },
    { key: "slack", label: __("Slack", "nxp-easy-forms") },
    { key: "teams", label: __("Microsoft Teams", "nxp-easy-forms") },
    { key: "joomla_article", label: __("Joomla Article", "nxp-easy-forms") },
    { key: "user_registration", label: __("User Registration", "nxp-easy-forms") },
    { key: "user_login", label: __("User Login", "nxp-easy-forms") },
    { key: "mailchimp", label: __("Mailchimp", "nxp-easy-forms") },
    { key: "salesforce", label: __("Salesforce", "nxp-easy-forms") },
    { key: "hubspot", label: __("HubSpot", "nxp-easy-forms") },
];

const formTitle = computed({
    get: () => store.title,
    set: (value) => {
        store.title = value;
        store.hasUnsavedChanges = true;
    },
});

const formAlias = computed({
    get: () => store.alias,
    set: (value) => {
        store.alias = value;
        store.hasUnsavedChanges = true;
    },
});

const selectedField = computed(
    () =>
        store.fields.find((field) => field.id === selectedFieldId.value) ||
        null,
);

const enabledIntegrationTags = computed(() => {
    if (!formId.value) {
        return [];
    }

    const integrations = options.value?.integrations;

    if (!integrations || typeof integrations !== "object") {
        return [];
    }

    return integrationTagDefinitions
        .filter(({ key }) => integrations?.[key]?.enabled === true)
        .map(({ key, label }) => ({ key, label }));
});

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

const ensureHiddenInputs = () => {
    if (typeof document === "undefined") {
        return;
    }

    const formEl = document.getElementById("adminForm");

    if (!formEl) {
        hiddenFieldInputs.title = null;
        hiddenFieldInputs.alias = null;
        hiddenFieldInputs.fields = null;
        hiddenFieldInputs.settings = null;
        return;
    }

    hiddenFieldInputs.title = hiddenFieldInputs.title || formEl.querySelector("input[name=\"jform[title]\"]");
    hiddenFieldInputs.alias = hiddenFieldInputs.alias || formEl.querySelector("input[name=\"jform[alias]\"]");
    hiddenFieldInputs.fields = hiddenFieldInputs.fields || formEl.querySelector("input[name=\"jform[fields]\"]");
    hiddenFieldInputs.settings = hiddenFieldInputs.settings || formEl.querySelector("input[name=\"jform[settings]\"]");
};

const serialiseFields = () => {
    try {
        return JSON.stringify(store.fields ?? []);
    } catch (error) {
        return "[]";
    }
};

const serialiseOptions = () => {
    try {
        return JSON.stringify(store.options ?? {});
    } catch (error) {
        return "{}";
    }
};

const syncHiddenInputs = () => {
    ensureHiddenInputs();

    if (hiddenFieldInputs.title) {
        hiddenFieldInputs.title.value = store.title || "";
    }

    if (hiddenFieldInputs.alias) {
        hiddenFieldInputs.alias.value = store.alias || "";
    }

    if (hiddenFieldInputs.fields) {
        hiddenFieldInputs.fields.value = serialiseFields();
    }

    if (hiddenFieldInputs.settings) {
        hiddenFieldInputs.settings.value = serialiseOptions();
    }
};

onMounted(async () => {
    await store.bootstrap();
    nextTick(() => {
        syncHiddenInputs();
    });
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
        const footerEl = document.getElementById("wpfooter");
        if (footerEl) ro.observe(footerEl);
    }

    nextTick(syncHiddenInputs);
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
    () => store.title,
    () => {
        syncHiddenInputs();
    },
);

watch(
    () => store.alias,
    () => {
        syncHiddenInputs();
    },
);

watch(
    () => store.fields,
    () => {
        syncHiddenInputs();
    },
    { deep: true }
);

watch(
    options,
    () => {
        syncHiddenInputs();
    },
    { deep: true }
);

watch(
    formId,
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
    align-items: center;
}

.nxp-builder__actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 44px;
}

.nxp-builder__actions .btn-outline-secondary {
    color: var(--bs-body-color);
    background-color: var(--nxp-panel-bg);
    border-color: var(--nxp-surface-border);
}

.nxp-builder__actions .btn-outline-secondary:hover,
.nxp-builder__actions .btn-outline-secondary:focus {
    color: var(--bs-primary);
    border-color: var(--bs-primary);
    background-color: var(--bs-secondary-bg, rgba(0, 0, 0, 0.04));
}

.nxp-builder__actions .btn[disabled] {
    opacity: 0.65;
    pointer-events: none;
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

.nxp-builder__preview .fa-regular,
.nxp-builder__preview .fa-solid {
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
    filter: var(--nxp-icon-filter);
    transition: filter 0.2s ease;
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
    /* Tweak these offsets if Joomla admin bars/toolbars change
       Subtract the Joomla admin footer safe area (~65px) to avoid bottom cut-off */
    max-height: calc(100vh - 180px - 65px);
    overflow: hidden;
}

.nxp-builder__loading {
    padding: 60px;
    text-align: center;
    font-size: 1.2rem;
}

.nxp-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
    border-radius: 8px;
}

.nxp-alert__icon {
    font-size: 18px;
    line-height: 1;
    margin-top: 2px;
}

.nxp-alert__message {
    flex: 1;
    margin: 0;
    line-height: 1.6;
}

.nxp-alert__dismiss {
    background: transparent;
    border: none;
    color: inherit;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
}

.nxp-alert__dismiss:hover,
.nxp-alert__dismiss:focus-visible {
    opacity: 0.75;
    outline: none;
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

.nxp-quick-start__icon .fa-regular,
.nxp-quick-start__icon .fa-solid {
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

.nxp-quick-start__dismiss .fa-regular,
.nxp-quick-start__dismiss .fa-solid {
    width: 20px;
    height: 20px;
    font-size: 20px;
}
</style>
