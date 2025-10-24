<template>
    <div
        class="nxp-modal-backdrop"
        v-if="visible"
        @click.self="$emit('close')"
        role="dialog"
        aria-labelledby="nxp-preview-title"
        aria-modal="true"
    >
        <div class="nxp-preview-modal">
            <div class="nxp-preview-modal__header">
                <h2 id="nxp-preview-title">{{ __("Form Preview", "nxp-easy-forms") }}</h2>
                <button
                    type="button"
                    class="nxp-modal__close"
                    @click="$emit('close')"
                    :title="__('Close', 'nxp-easy-forms')"
                >
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <div class="nxp-preview-modal__body">
                <div class="nxp-preview-notice">
                    <span class="dashicons dashicons-info"></span>
                    {{ __("This is an approximate visual preview. The final look will adapt to your current theme. The form is not functional in preview mode.", "nxp-easy-forms") }}
                </div>

                <div class="nxp-preview-container">
                    <div class="nxp-preview-form" :style="previewStyle">
                        <form class="nxp-easy-form" @submit.prevent>
                            <h2 v-if="formTitle" class="nxp-preview-form__title">
                                {{ formTitle }}
                            </h2>

                            <div
                                v-for="field in previewFields"
                                :key="field.id"
                                class="nxp-form-field"
                                :class="`nxp-form-field--${field.type}`"
                            >
                                <template v-if="field.type === 'text_block'">
                                    <div class="nxp-form-text-block" v-html="field.content"></div>
                                </template>

                                <template v-else-if="field.type === 'submit'">
                                    <button type="submit" class="nxp-form-submit" disabled>
                                        {{ field.label || __("Submit", "nxp-easy-forms") }}
                                    </button>
                                </template>

                                <template v-else>
                                    <label v-if="field.label" class="nxp-form-label">
                                        {{ field.label }}
                                        <span v-if="field.required" class="nxp-form-required">*</span>
                                    </label>

                                    <template v-if="field.type === 'textarea'">
                                        <textarea
                                            :placeholder="field.placeholder"
                                            :rows="field.rows || 4"
                                            disabled
                                        ></textarea>
                                    </template>

                                    <template v-else-if="field.type === 'select'">
                                        <select disabled>
                                            <option value="">
                                                {{ field.placeholder || __("Select an option", "nxp-easy-forms") }}
                                            </option>
                                            <option
                                                v-for="(option, idx) in field.options"
                                                :key="idx"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </template>

                                    <template v-else-if="field.type === 'radio'">
                                        <div class="nxp-form-radio-group">
                                            <label
                                                v-for="(option, idx) in field.options"
                                                :key="idx"
                                                class="nxp-form-radio"
                                            >
                                                <input
                                                    type="radio"
                                                    :name="field.name"
                                                    :value="option.value"
                                                    disabled
                                                />
                                                <span>{{ option.label }}</span>
                                            </label>
                                        </div>
                                    </template>

                                    <template v-else-if="field.type === 'checkbox'">
                                        <div class="nxp-form-checkbox-group">
                                            <label
                                                v-for="(option, idx) in field.options"
                                                :key="idx"
                                                class="nxp-form-checkbox"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :name="field.name + '[]'"
                                                    :value="option.value"
                                                    disabled
                                                />
                                                <span>{{ option.label }}</span>
                                            </label>
                                        </div>
                                    </template>

                                    <template v-else-if="field.type === 'file'">
                                        <input type="file" disabled />
                                    </template>

                                    <template v-else>
                                        <input
                                            :type="getInputType(field.type)"
                                            :placeholder="field.placeholder"
                                            disabled
                                        />
                                    </template>

                                    <p v-if="field.description" class="nxp-form-description">
                                        {{ field.description }}
                                    </p>
                                </template>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="nxp-preview-zoom">
                    <label>
                        {{ __("Zoom:", "nxp-easy-forms") }}
                        <input
                            type="range"
                            min="50"
                            max="100"
                            step="10"
                            v-model.number="zoomLevel"
                        />
                        <span>{{ zoomLevel }}%</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { __ } from "@/utils/i18n";

const props = defineProps({
    visible: Boolean,
    formTitle: String,
    fields: Array,
});

defineEmits(["close"]);

const zoomLevel = ref(70);

const previewStyle = computed(() => ({
    transform: `scale(${zoomLevel.value / 100})`,
    transformOrigin: "top center",
}));

const previewFields = computed(() => {
    return props.fields.filter(
        (field) => field.type !== "hidden"
    );
});

const getInputType = (fieldType) => {
    const typeMap = {
        text: "text",
        email: "email",
        tel: "tel",
        password: "password",
        date: "date",
        number: "number",
    };
    return typeMap[fieldType] || "text";
};
</script>

<style scoped>
.nxp-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.nxp-preview-modal {
    background: #fff;
    border-radius: 8px;
    width: 90vw;
    max-width: 1200px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.nxp-preview-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #ddd;
}

.nxp-preview-modal__header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.nxp-preview-modal__body {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.nxp-preview-notice {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: #e8f5fa;
    border-left: 4px solid #0073aa;
    border-radius: 4px;
    color: #135e96;
    font-size: 14px;
}

.nxp-preview-notice .dashicons {
    flex-shrink: 0;
    color: #0073aa;
}

.nxp-preview-container {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 20px;
    background: #f5f5f5;
    border-radius: 4px;
    /* Avoid nested scrollbars; the modal body scrolls instead */
    overflow: visible;
}

.nxp-preview-form {
    width: 100%;
    max-width: 600px;
    background: #fff;
    padding: 32px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
    /* Ensure no implicit max-height constraints can clip content */
    max-height: none !important;
    overflow: visible;
}

.nxp-preview-form__title {
    margin: 0 0 24px;
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.nxp-easy-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.nxp-form-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.nxp-form-label {
    font-weight: 500;
    color: #1d2327;
    font-size: 14px;
}

.nxp-form-required {
    color: #d63638;
    margin-left: 2px;
}

.nxp-form-field input[type="text"],
.nxp-form-field input[type="email"],
.nxp-form-field input[type="tel"],
.nxp-form-field input[type="password"],
.nxp-form-field input[type="date"],
.nxp-form-field input[type="number"],
.nxp-form-field textarea,
.nxp-form-field select {
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
    background: #fff;
    color: #1d2327;
    cursor: not-allowed;
    opacity: 0.7;
}

.nxp-form-field textarea {
    resize: vertical;
}

.nxp-form-radio-group,
.nxp-form-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.nxp-form-radio,
.nxp-form-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: not-allowed;
}

.nxp-form-radio input,
.nxp-form-checkbox input {
    cursor: not-allowed;
}

.nxp-form-text-block {
    color: #50575e;
    line-height: 1.6;
}

.nxp-form-description {
    margin: 0;
    font-size: 13px;
    color: #646970;
}

.nxp-form-submit {
    padding: 10px 24px;
    background: #2271b1;
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: not-allowed;
    opacity: 0.7;
}

.nxp-preview-zoom {
    display: flex;
    justify-content: center;
    padding-top: 16px;
    border-top: 1px solid #ddd;
}

.nxp-preview-zoom label {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
}

.nxp-preview-zoom input[type="range"] {
    width: 200px;
}

.nxp-preview-zoom span {
    min-width: 45px;
    text-align: right;
    color: #646970;
}

/* Ensure preview ignores builder-specific height/overflow constraints */
.nxp-preview-modal :deep(.nxp-builder__layout .nxp-panel) {
    min-height: auto !important;
    max-height: none !important;
    overflow: visible !important;
}

.nxp-preview-modal :deep(.nxp-panel__list) {
    flex: initial !important;
    min-height: auto !important;
    overflow: visible !important;
}

.nxp-preview-modal :deep(.nxp-canvas) {
    min-height: auto !important;
    max-height: none !important;
    overflow: visible !important;
}

.nxp-preview-modal :deep(.nxp-canvas__board) {
    max-height: none !important;
    overflow: visible !important;
}
</style>
