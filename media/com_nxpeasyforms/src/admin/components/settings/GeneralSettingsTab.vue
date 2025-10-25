<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-general"
        id="nxp-panel-general"
    >
        <label class="nxp-setting nxp-setting--switch">
            <span>{{ __("Store submissions in Joomla") }}</span>
            <input type="checkbox" v-model="local.store_submissions" />
        </label>

        <div class="nxp-setting">
            <span>{{ __("Success message") }}</span>
            <textarea rows="3" v-model="local.success_message"></textarea>
        </div>

        <div class="nxp-setting">
            <span>{{ __("Error message") }}</span>
            <textarea rows="3" v-model="local.error_message"></textarea>
        </div>
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
