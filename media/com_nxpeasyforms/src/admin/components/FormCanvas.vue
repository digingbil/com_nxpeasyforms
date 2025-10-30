<template>
    <section class="nxp-canvas">
        <header class="nxp-canvas__header">
            <h2>{{ __("Your Form") }}</h2>
            <p>
                {{
                    __(
                        "Reorder fields by drag and drop. Click on the field name to edit its settings.",
                    )
                }}
            </p>
            <p
                v-if="integrationTags.length"
                class="nxp-canvas__integrations"
                :aria-label="__('Enabled integrations', 'nxp-easy-forms')"
            >
                <span
                    v-for="tag in integrationTags"
                    :key="tag.key"
                    class="nxp-canvas__integration-tag"
                >
                    {{ tag.label }}
                </span>
            </p>
            <button
                type="button"
                class="button button-secondary nxp-canvas__settings"
                @click="$emit('open-settings')"
                :title="__('Form settings', 'nxp-easy-forms')"
                :aria-label="__('Form settings', 'nxp-easy-forms')"
            >
                <img
                    class="nxp-canvas__settings-icon"
                    :src="ICON_SETTINGS"
                    alt=""
                    aria-hidden="true"
                />
            </button>
        </header>
        <div
            ref="canvasRef"
            class="nxp-canvas__board"
            :class="{ 'nxp-canvas__board--empty': !fields.length }"
        >
            <div v-if="!fields.length" class="nxp-canvas__empty">
                {{ __("Drag fields here to start building your form.") }}
            </div>
            <article
                v-for="(field, index) in fields"
                :key="field.id"
                class="nxp-field"
                :class="{ 'nxp-field--selected': field.id === selectedId }"
                :data-field-id="field.id"
                :data-id="field.id"
            >
                <div class="nxp-field__main" @click="emitSelect(field.id)">
                    <span class="nxp-field__drag" aria-hidden="true">⋮⋮</span>
                    <img
                        class="nxp-field__icon"
                        :src="getIcon(field.type)"
                        alt=""
                        aria-hidden="true"
                    />
                    <div class="nxp-field__content">
                        <button
                            type="button"
                            class="nxp-field__label-button"
                            @click.stop="emitSelect(field.id)"
                            :aria-pressed="field.id === selectedId"
                            :aria-label="field.label ? field.label : __('Edit field', 'nxp-easy-forms')"
                        >
                            <span class="nxp-field__label-text">{{
                                field.label || __("Label", "nxp-easy-forms")
                            }}</span>
                            <img class="nxp-field__label-icon" :src="ICON_EDIT" alt="" aria-hidden="true" />
                        </button>
                        <small class="nxp-field__meta">
                            <template v-if="field.type === 'custom_text'">
                                {{ __("Static content", "nxp-easy-forms") }}
                            </template>
                            <template v-else-if="field.type === 'hidden'">
                                {{ __("Hidden field", "nxp-easy-forms") }}
                            </template>
                            <template v-else>
                                {{ field.type }} ·
                                {{
                                    field.required
                                        ? __("Required")
                                        : __("Optional")
                                }}
                            </template>
                        </small>
                    </div>
                </div>
                <div class="nxp-field__actions">
                    <button
                        type="button"
                        class="button button-small nxp-field__action"
                        @click="emitDuplicate(field.id)"
                    >
                        {{ __("Duplicate") }}
                    </button>
                    <button
                        type="button"
                        class="button button-link-delete nxp-field__action nxp-field__action--danger"
                        @click="emitRemove(field.id)"
                    >
                        {{ __("Remove") }}
                    </button>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from "vue";
import Sortable from "sortablejs";
import { __ } from "@/utils/translate";
import ICON_SETTINGS from "../../../assets/icons/settings-cog.svg";
// Icon imports (resolved to URLs by the bundler)
import ICON_TEXT from "../../../assets/icons/input-check.svg";
import ICON_EMAIL from "../../../assets/icons/input-spark.svg";
import ICON_TEL from "../../../assets/icons/device-mobile-rotated.svg";
import ICON_TEXTAREA from "../../../assets/icons/text-resize.svg";
import ICON_SELECT from "../../../assets/icons/selector.svg";
import ICON_RADIO from "../../../assets/icons/gradienter.svg";
import ICON_CHECKBOX from "../../../assets/icons/checkbox.svg";
import ICON_PASSWORD from "../../../assets/icons/password.svg";
import ICON_FILE from "../../../assets/icons/upload.svg";
import ICON_DATE from "../../../assets/icons/calendar.svg";
import ICON_COUNTRY from "../../../assets/icons/flag-code.svg";
import ICON_STATE from "../../../assets/icons/building-stadium.svg";
import ICON_CUSTOM_TEXT from "../../../assets/icons/align-justified.svg";
import ICON_BUTTON from "../../../assets/icons/devices-share.svg";
import ICON_HIDDEN from "../../../assets/icons/eye-closed.svg";
import ICON_EDIT from "../../../assets/icons/edit.svg";

const props = defineProps({
    fields: {
        type: Array,
        required: true,
    },
    selectedId: {
        type: String,
        default: null,
    },
    integrationTags: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    "select",
    "remove",
    "duplicate",
    "reorder",
    "create-field",
]);
const canvasRef = ref(null);
let sortableInstance = null;

const emitSelect = (id) => emit("select", id);
const emitRemove = (id) => emit("remove", id);
const emitDuplicate = (id) => emit("duplicate", id);

const ICONS = {
    text: ICON_TEXT,
    email: ICON_EMAIL,
    tel: ICON_TEL,
    textarea: ICON_TEXTAREA,
    select: ICON_SELECT,
    radio: ICON_RADIO,
    checkbox: ICON_CHECKBOX,
    password: ICON_PASSWORD,
    file: ICON_FILE,
    date: ICON_DATE,
    country: ICON_COUNTRY,
    state: ICON_STATE,
    custom_text: ICON_CUSTOM_TEXT,
    button: ICON_BUTTON,
    hidden: ICON_HIDDEN,
};

const getIcon = (type) => ICONS[type] || ICON_TEXT;

onMounted(() => {
    if (!canvasRef.value) {
        return;
    }

    sortableInstance = Sortable.create(canvasRef.value, {
        group: {
            name: "nxp-fields",
            pull: true,
            put: true,
        },
        handle: ".nxp-field__drag",
        animation: 150,
        ghostClass: "nxp-field--ghost",
        onAdd(evt) {
            const type = evt.item?.dataset?.fieldType;
            if (type) {
                emit("create-field", {
                    type,
                    index: evt.newIndex,
                });
            }
            if (evt.item && evt.item.parentNode) {
                evt.item.parentNode.removeChild(evt.item);
            }
        },
        onEnd(evt) {
            if (
                typeof evt.oldIndex === "undefined" ||
                typeof evt.newIndex === "undefined"
            ) {
                return;
            }
            if (evt.from !== evt.to) {
                return;
            }
            if (evt.oldIndex === evt.newIndex) {
                return;
            }
            emit("reorder", {
                oldIndex: evt.oldIndex,
                newIndex: evt.newIndex,
            });
        },
    });
});

onBeforeUnmount(() => {
    if (sortableInstance) {
        sortableInstance.destroy();
        sortableInstance = null;
    }
});

watch(
    () => props.fields.map((f) => f.id).join(','),
    () => {
        if (!sortableInstance) return;
        sortableInstance.sort(props.fields.map((field) => field.id));
    },
);
</script>

<style scoped>
.nxp-canvas {
    background: var(--nxp-panel-bg);
    border: 1px solid var(--nxp-surface-border);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    height: 100%;
    /* Constrain height so side panels (like the field palette) can scroll internally */
    min-height: 520px;
    /* Tweak these offsets if Joomla admin bars/toolbars change
       Subtract the Joomla admin footer safe area (~65px) to avoid bottom cut-off */
    max-height: calc(100vh - 180px - 65px);
    overflow: hidden;
}

.nxp-canvas__header h2 {
    margin: 0;
    font-size: 1.25rem;
}

.nxp-canvas__header {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: start;
    gap: 4px 12px;
    position: relative;
}

.nxp-canvas__header h2 {
    grid-column: 1 / 2;
}

.nxp-canvas__header p {
    margin: 4px 0 12px;
    color: var(--nxp-muted-color);
    font-size: 1rem;
    grid-column: 1 / 2;
}

.nxp-canvas__integrations {
    grid-column: 1 / 2;
    margin: 0 0 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--nxp-muted-color);
}

.nxp-canvas__integration-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--nxp-chip-bg, rgba(0, 0, 0, 0.06));
    color: var(--nxp-chip-color, var(--nxp-muted-color));
    border: 1px solid var(--nxp-surface-border);
    font-size: 0.75rem;
    line-height: 1.2;
    font-weight: 500;
    letter-spacing: 0.01em;
}

.nxp-canvas__settings {
    grid-column: 2 / 3;
    justify-self: end;
    align-self: start;
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border-radius: 50%;
    background: transparent;
    border: none;
    position: absolute;
    top: -10px;
    right: -10px;
}

.nxp-canvas__settings:focus {
    outline-offset: 2px;
}
.nxp-canvas__settings:hover {
    background: var(--bs-secondary-bg, rgba(0, 0, 0, 0.04));
}

.nxp-canvas__board {
    flex: 1;
    border: 1px dashed var(--nxp-surface-border);
    border-radius: 10px;
    padding: 12px;
    max-height: inherit;
    overflow: auto;
}

.nxp-canvas__board--empty {
    display: flex;
    align-items: center;
    justify-content: center;
}

.nxp-canvas__empty {
    color: var(--nxp-muted-color);
    font-size: 1.05rem;
    text-align: center;
}

.nxp-field {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid var(--nxp-surface-border);
    border-radius: 8px;
    padding: 12px;
    background: var(--nxp-card-bg);
    margin-bottom: 8px;
}

.nxp-field:last-child {
    margin-bottom: 0;
}

.nxp-field--selected {
    border-color: var(--nxp-hover-border);
    box-shadow: 0 0 0 1px var(--nxp-hover-shadow);
}

.nxp-field--ghost {
    opacity: 0.4;
}

.nxp-field__main {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.nxp-field__drag {
    font-size: 1.2rem;
    cursor: grab;
    color: var(--nxp-muted-color);
}

.nxp-field__content {
    display: flex;
    flex-direction: column;
}

.nxp-field__content strong {
    font-size: 1.05rem;
}

.nxp-field__meta {
    color: var(--nxp-muted-color);
    font-size: 0.9rem;
}

.nxp-field__actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.nxp-field__action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid var(--nxp-surface-border);
    background: var(--nxp-panel-bg);
    color: var(--bs-body-color);
    transition:
        color 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
    text-decoration: none;
    min-height: 34px;
}

.nxp-field__action:hover,
.nxp-field__action:focus-visible {
    color: var(--bs-primary, #2271b1);
    border-color: var(--nxp-hover-border);
    box-shadow: 0 0 0 1px var(--nxp-hover-shadow);
    outline: none;
}

.nxp-field__action:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
}

.nxp-field__action--danger {
    border-color: transparent;
    color: var(--bs-danger, #b32d2e);
    background: transparent;
}

.nxp-field__action--danger:hover,
.nxp-field__action--danger:focus-visible {
    color: var(--bs-danger, #b32d2e);
    background: rgba(220, 53, 69, 0.12);
    border-color: rgba(220, 53, 69, 0.35);
    box-shadow: none;
}

.nxp-field__label-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border: none;
    padding: 0;
    margin: 0;
    color: inherit;
    cursor: pointer;
    text-align: left;
}

.nxp-field__label-button:focus {
    outline: 2px solid var(--nxp-hover-border);
    outline-offset: 2px;
    border-radius: 4px;
}

.nxp-field__label-button .nxp-field__label-text{
    font-weight: 600;
    font-size: 1rem;
}

.nxp-field__label-button:hover .nxp-field__label-text {
    text-decoration: underline;
}

.nxp-field__label-icon {
    width: 14px;
    height: 14px;
    opacity: 0.85;
    filter: var(--nxp-icon-filter);
    transition: filter 0.2s ease;
}

.nxp-field__icon {
    width: 20px;
    height: 20px;
    opacity: 0.9;
    filter: var(--nxp-icon-filter);
    transition: filter 0.2s ease;
}

.nxp-canvas__settings-icon {
    width: 26px;
    height: 26px;
    filter: var(--nxp-icon-filter);
    transition: filter 0.2s ease;
}
</style>
