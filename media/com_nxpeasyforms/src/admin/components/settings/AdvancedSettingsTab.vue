<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-advanced"
        id="nxp-panel-advanced"
    >
                                <div class="nxp-setting">
                                    <span>{{
                                        __("Custom CSS", "nxp-easy-forms")
                                    }}</span>
                                    <textarea
                                        rows="6"
                                        v-model="local.custom_css"
                                        spellcheck="false"
                                    ></textarea>
                                    <small class="nxp-setting__hint">
                                        {{
                                            __(
                                                "CSS is scoped to the form wrapper and injected inline on render.",
                                                "nxp-easy-forms",
                                            )
                                        }}
                                    </small>
                                </div>
        
    </div>
</template>

<script setup>
import { inject } from "vue";
import { __ } from "@/utils/translate";

const ctx = inject("formSettingsContext");

if (!ctx) {
    throw new Error("Form settings context not provided");
}

const { local } = ctx;
</script>
