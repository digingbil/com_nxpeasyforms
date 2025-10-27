<template>
    <section class="nxp-panel">
        <header class="nxp-panel__header">
            <h2>{{ __("Fields") }}</h2>
            <p>{{ __("Drag a field into your form or click to add it.") }}</p>
        </header>
        <ul ref="paletteRef" class="nxp-panel__list">
            <li
                v-for="field in library"
                :key="field.type"
                class="nxp-panel__item"
                :data-field-type="field.type"
                @click="handleClick(field.type)"
            >
                <img
                    class="nxp-panel__item-icon"
                    :src="getIcon(field.type)"
                    alt=""
                    aria-hidden="true"
                />
                <div class="nxp-panel__item-body">
                    <div class="nxp-panel__item-title">{{ field.label }}</div>
                    <div class="nxp-panel__item-desc">{{ field.description }}</div>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";
import Sortable from "sortablejs";
import { FIELD_LIBRARY } from "@/admin/constants/fields";
import { __ } from "@/utils/translate";

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

const emit = defineEmits(["add-field"]);
const library = FIELD_LIBRARY;
const paletteRef = ref(null);
let sortableInstance = null;

const handleClick = (type) => {
    emit("add-field", type);
};

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
    if (!paletteRef.value) {
        return;
    }

    sortableInstance = Sortable.create(paletteRef.value, {
        group: {
            name: "nxp-fields",
            pull: "clone",
            put: false,
        },
        sort: false,
        animation: 150,
        ghostClass: "nxp-panel__item--ghost",
        setData(dataTransfer, dragEl) {
            dataTransfer.setData("text/plain", dragEl.dataset.fieldType || "");
        },
    });
});

onBeforeUnmount(() => {
    if (sortableInstance) {
        sortableInstance.destroy();
        sortableInstance = null;
    }
});
</script>

<style scoped>
.nxp-panel {
    background: var(--nxp-panel-bg);
    border: 1px solid var(--nxp-surface-border);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.nxp-panel__header h2 {
    font-size: 1.25rem;
    margin: 0;
}

.nxp-panel__header p {
    color: var(--nxp-muted-color);
    margin: 4px 0 12px;
    font-size: 1rem;
}

.nxp-panel__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
    /* Critical: allow the list to take remaining vertical space and scroll */
    flex: 1 1 auto;
    min-height: 0; /* allow shrinking inside a constrained parent */
    overflow-y: auto;
}

.nxp-panel__item {
    border: 1px solid var(--nxp-surface-border);
    border-radius: 8px;
    padding: 12px;
    cursor: grab;
    background: var(--nxp-card-bg);
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
    display: grid;
    grid-template-columns: 24px 1fr;
    align-items: start;
    gap: 10px;
}

.nxp-panel__item:hover {
    border-color: var(--nxp-hover-border);
    box-shadow: 0 0 0 1px var(--nxp-hover-shadow);
}

.nxp-panel__item-title {
    font-weight: 600;
    font-size: 1rem;
}

.nxp-panel__item-desc {
    color: var(--nxp-muted-color);
    font-size: 0.95rem;
}

.nxp-panel__item--ghost {
    opacity: 0.4;
}

.nxp-panel__item-icon {
    width: 20px;
    height: 20px;
    margin-top: 2px;
    opacity: 0.9;
    filter: var(--nxp-icon-filter);
    transition: filter 0.2s ease;
}

.nxp-panel__item-body {
    display: grid;
    gap: 4px;
}
</style>
