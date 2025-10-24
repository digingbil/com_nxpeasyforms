<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-privacy"
        id="nxp-panel-privacy"
    >
        <label class="nxp-setting">
            <span>{{ __("IP address storage", "nxp-easy-forms") }}</span>
            <select v-model="local.ip_storage">
                <option value="full">
                    {{ __("Store full IP address", "nxp-easy-forms") }}
                </option>
                <option value="anonymous">
                    {{ __("Store anonymized IP address", "nxp-easy-forms") }}
                </option>
                <option value="none">
                    {{ __("Do not store IP addresses", "nxp-easy-forms") }}
                </option>
            </select>
            <small class="nxp-setting__hint">
                {{ __("An anonymized IP removes the last part of the address before saving.", "nxp-easy-forms") }}
            </small>
        </label>

        <label class="nxp-setting nxp-setting--switch">
            <span>{{ __("Automatically delete old submissions", "nxp-easy-forms") }}</span>
            <input type="checkbox" v-model="local.delete_submissions_after_days_enabled" />
        </label>

        <label v-if="local.delete_submissions_after_days_enabled" class="nxp-setting">
            <span>{{ __("Delete submissions older than (days)", "nxp-easy-forms") }}</span>
            <input
                type="number"
                min="1"
                max="3650"
                v-model.number="local.delete_submissions_after_days"
            />
            <small class="nxp-setting__hint">
                {{ __("Submissions older than this many days will be automatically deleted. Runs daily.", "nxp-easy-forms") }}
            </small>
        </label>
    </div>
</template>

<script setup>
import { inject } from "vue";
import { __ } from "@/utils/i18n";

const ctx = inject("formSettingsContext");

if (!ctx) {
    throw new Error("Form settings context not provided");
}

const { local } = ctx;
</script>
