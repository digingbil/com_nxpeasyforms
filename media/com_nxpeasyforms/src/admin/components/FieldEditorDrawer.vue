<template>
    <div class="nxp-drawer" :class="{ 'nxp-drawer--open': Boolean(field) }">
        <div v-if="field" class="nxp-drawer__inner">
            <header class="nxp-drawer__header">
                <h3>{{ field.label || __("Field settings") }}</h3>
                <button
                    type="button"
                    class="button button-link nxp-close-drawer"
                    @click="emit('close')"
                >
                    ×
                </button>
            </header>
            <div class="nxp-drawer__content">
                <label class="nxp-control">
                    <span>{{ __("Field label") }}</span>
                    <input
                        type="text"
                        v-model="local.label"
                        @input="commit()"
                    />
                </label>
                <label class="nxp-control" v-if="!isStatic">
                    <span>{{ __("Field name") }}</span>
                    <input type="text" v-model="local.name" @input="commit()" />
                </label>
                <label class="nxp-control" v-if="supportsPlaceholder">
                    <span>{{ __("Placeholder") }}</span>
                    <input
                        type="text"
                        v-model="local.placeholder"
                        @input="commit()"
                    />
                </label>
                <label class="nxp-control" v-if="supportsRequired">
                    <span class="nxp-control__switch">
                        <input
                            type="checkbox"
                            v-model="local.required"
                            @change="commit()"
                        />
                        {{ __("Required") }}
                    </span>
                </label>
                <div class="nxp-control" v-if="supportsAccept">
                    <span>{{
                        __("Allowed file types", "nxp-easy-forms")
                    }}</span>
                    <label class="nxp-control__switch">
                        <input
                            type="checkbox"
                            v-model="local.acceptImages"
                            @change="commit()"
                        />
                        {{ __("Images", "nxp-easy-forms") }}
                    </label>
                    <label class="nxp-control__switch">
                        <input
                            type="checkbox"
                            v-model="local.acceptDocuments"
                            @change="commit()"
                        />
                        {{ __("Documents (PDF, Word, Text)", "nxp-easy-forms") }}
                    </label>
                    <label class="nxp-control__switch">
                        <input
                            type="checkbox"
                            v-model="local.acceptSpreadsheets"
                            @change="commit()"
                        />
                        {{ __("Spreadsheets (Excel)", "nxp-easy-forms") }}
                    </label>
                    <small class="nxp-drawer__hint">
                        {{
                            __(
                                "Select at least one file type",
                                "nxp-easy-forms",
                            )
                        }}
                    </small>
                </div>
                <label class="nxp-control" v-if="supportsAccept">
                    <span>{{
                        __("Max file size (MB)", "nxp-easy-forms")
                    }}</span>
                    <input
                        type="number"
                        v-model.number="local.maxFileSize"
                        @input="commit()"
                        min="1"
                        max="50"
                        step="1"
                    />
                    <small class="nxp-drawer__hint">
                        {{
                            __(
                                "Maximum: 50MB. Default: 5MB.",
                                "nxp-easy-forms",
                            )
                        }}
                    </small>
                </label>
                <label class="nxp-control" v-if="local.type === 'custom_text'">
                    <span>{{ __("Content", "nxp-easy-forms") }}</span>
                    <textarea
                        rows="6"
                        v-model="local.content"
                        @input="commit()"
                    ></textarea>
                </label>
                <div
                    class="nxp-control"
                    v-if="['select', 'radio'].includes(local.type)"
                >
                    <span>{{ __("Options (one per line)") }}</span>
                    <textarea
                        rows="5"
                        v-model="optionsText"
                        @input="updateOptions"
                    ></textarea>
                </div>
                <label class="nxp-control" v-if="local.type === 'select'">
                    <span class="nxp-control__switch">
                        <input
                            type="checkbox"
                            v-model="local.multiple"
                            @change="commit()"
                        />
                        {{ __("Allow multiple selections", "nxp-easy-forms") }}
                    </span>
                </label>

                <div class="nxp-control" v-if="local.type === 'state'">
                    <label>
                        <span>{{ __("Linked country field", "nxp-easy-forms") }}</span>
                        <select v-model="local.country_field" @change="commit()">
                            <option value="">{{ __("Select country field", "nxp-easy-forms") }}</option>
                            <option
                                v-for="field in countryFields"
                                :key="field.name"
                                :value="field.name"
                            >
                                {{ field.label || field.name }}
                            </option>
                        </select>
                        <small class="nxp-drawer__hint">
                            {{ __("States will update when the selected country changes.", "nxp-easy-forms") }}
                        </small>
                    </label>
                    <label class="nxp-control__switch">
                        <input
                            type="checkbox"
                            v-model="local.allow_text_input"
                            @change="commit()"
                        />
                        {{ __("Allow text input for countries without states", "nxp-easy-forms") }}
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { __ } from "@/utils/i18n";

const props = defineProps({
    field: {
        type: Object,
        default: null,
    },
    fields: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update", "close"]);

const local = reactive({
    id: null,
    type: "",
    label: "",
    name: "",
    placeholder: "",
    required: true,
    options: [],
    multiple: false,
    content: "",
    accept: "",
    acceptImages: false,
    acceptDocuments: false,
    acceptSpreadsheets: false,
    maxFileSize: 5,
    country_field: "",
    allow_text_input: true,
});

const optionsText = computed({
    get() {
        return (local.options || []).join("\n");
    },
    set(value) {
        local.options = value
            .split("\n")
            .map((entry) => entry.trim())
            .filter(Boolean);
    },
});

const supportsPlaceholder = computed(
    () =>
        ![
            "checkbox",
            "button",
            "custom_text",
            "file",
            "date",
            "radio",
        ].includes(local.type),
);
const supportsRequired = computed(
    () => !["button", "custom_text"].includes(local.type),
);
const supportsAccept = computed(() => local.type === "file");
const isStatic = computed(() => ["button", "custom_text"].includes(local.type));
const countryFields = computed(() => {
    return (props.fields || []).filter((f) => f.type === "country");
});

watch(
    () => props.field,
    (field) => {
        if (!field) {
            local.id = null;
            return;
        }

        Object.assign(local, {
            id: field.id,
            type: field.type,
            label: field.label,
            name: field.name,
            placeholder: field.placeholder,
            required: field.required,
            options: Array.isArray(field.options) ? [...field.options] : [],
            multiple: field.type === "select" ? !!field.multiple : false,
        });
        local.content = typeof field.content === "string" ? field.content : "";

        const acceptStr = typeof field.accept === "string" ? field.accept : "";
        local.accept = acceptStr;

        local.acceptImages = acceptStr.includes("image/");
        local.acceptDocuments = acceptStr.includes("application/pdf") || acceptStr.includes("application/msword");
        local.acceptSpreadsheets = acceptStr.includes("application/vnd.ms-excel") || acceptStr.includes("spreadsheet");

        local.maxFileSize = typeof field.maxFileSize === "number" && field.maxFileSize > 0 ? field.maxFileSize : 5;

        // State field properties
        local.country_field = field.type === "state" && typeof field.country_field === "string"
            ? field.country_field
            : "";
        local.allow_text_input = field.type === "state"
            ? field.allow_text_input !== false
            : true;
    },
    { immediate: true },
);


const commit = () => {
    if (!local.id) {
        return;
    }

    let acceptString = "";
    if (local.type === "file") {
        const acceptTypes = [];
        if (local.acceptImages) {
            acceptTypes.push("image/jpeg", "image/png", "image/gif", "image/webp");
        }
        if (local.acceptDocuments) {
            acceptTypes.push(
                "application/pdf",
                "application/msword",
                "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                "text/plain"
            );
        }
        if (local.acceptSpreadsheets) {
            acceptTypes.push(
                "application/vnd.ms-excel",
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                "text/csv"
            );
        }
        acceptString = acceptTypes.join(",");
    } else {
        acceptString = local.accept;
    }

    const multipleValue = local.type === "select" ? !!local.multiple : false;

    const updated = {
        ...local,
        options: [...(local.options || [])],
        multiple: multipleValue,
        accept: acceptString,
        maxFileSize: local.type === "file" ? Math.max(1, Math.min(50, local.maxFileSize || 5)) : undefined,
    };

    // Explicitly include country field properties
    if (local.type === "country") {
    }

    // Explicitly include state field properties
    if (local.type === "state") {
        updated.country_field = local.country_field || "";
        updated.allow_text_input = local.allow_text_input !== false;
    }

    // Dev-only debug removed to avoid "process is not defined" in browser builds

    emit("update", updated);
};

const updateOptions = () => {
    commit();
};
</script>

<style scoped>
.nxp-drawer {
    position: fixed;
    top: 32px;
    right: -360px;
    width: 320px;
    height: calc(100% - 32px);
    background: var(--nxp-panel-bg);
    border-left: 1px solid var(--nxp-surface-border);
    box-shadow: -4px 0 24px var(--nxp-drawer-shadow);
    transition: right 0.25s ease;
    z-index: 1000;
}

.nxp-close-drawer {
    width: 34px;
    height: 34px;
    text-align: center;
    border-radius: 50%;
    font-size: 24px;
    text-decoration: none;
    display: flex;
    align-content: center;
    justify-content: center;
    line-height: 32px;
}

.nxp-drawer--open {
    right: 0;
}

.nxp-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.nxp-drawer__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    border-bottom: 1px solid var(--nxp-surface-border);
}

.nxp-drawer__header h3 {
    font-size: 1.15rem;
    margin: 0;
}

.nxp-drawer__content {
    padding: 16px;
    display: grid;
    gap: 16px;
    overflow-y: auto;
}

.nxp-control {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.nxp-control > span {
    font-size: 0.98rem;
    font-weight: 500;
}

.nxp-drawer__hint {
    color: var(--nxp-muted-color);
    font-size: 0.92rem;
}


.nxp-drawer__hint--error {
    color: var(--bs-danger, #b32d2e);
}

.nxp-label-with-help {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.nxp-help {
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--nxp-help-bg);
    color: var(--nxp-help-color);
    font-size: 12px;
    line-height: 1;
    cursor: help;
    user-select: none;
}

.nxp-help-icon-wrap {
    display: inline-flex;
    align-items: center;
}

.nxp-help-icon {
    width: 18px;
    height: 18px;
    opacity: 0.9;
    cursor: help;
    filter: var(--nxp-icon-filter);
    transition: filter 0.2s ease;
}


.nxp-control__switch {
    display: flex;
    gap: 8px;
    align-items: center;
    font-size: 0.98rem;
}

input[type="text"],
input[type="number"],
textarea {
    width: 100%;
    font-size: 1rem;
    padding: 6px 8px;
}
</style>
