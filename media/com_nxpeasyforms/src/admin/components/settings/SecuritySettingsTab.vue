<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-security"
        id="nxp-panel-security"
    >
        <label class="nxp-setting nxp-setting--switch">
            <span>{{ __("Enable honeypot", "nxp-easy-forms") }}</span>
            <input type="checkbox" v-model="local.honeypot" />
        </label>

        <div class="nxp-setting">
            <span>{{ __("Captcha provider", "nxp-easy-forms") }}</span>
            <select v-model="local.captcha.provider">
                <option value="none">
                    {{ __("None (honeypot only)", "nxp-easy-forms") }}
                </option>
                <option value="recaptcha_v3">
                    {{ __("Google reCAPTCHA v3", "nxp-easy-forms") }}
                </option>
                <option value="turnstile">
                    {{ __("Cloudflare Turnstile", "nxp-easy-forms") }}
                </option>
                <option value="friendlycaptcha">
                    {{ __("Friendly Captcha", "nxp-easy-forms") }}
                </option>
            </select>
        </div>

        <div
            v-if="local.captcha.provider !== 'none'"
            class="nxp-setting-group"
        >
            <label class="nxp-setting">
                <span>{{ __("Site key", "nxp-easy-forms") }}</span>
                <input
                    type="text"
                    v-model="local.captcha.site_key"
                    autocomplete="off"
                />
            </label>
            <label class="nxp-setting">
                <span>{{ __("Secret key", "nxp-easy-forms") }}</span>
                <input
                    type="text"
                    v-model="local.captcha.secret_key"
                    autocomplete="off"
                />
            </label>
        </div>

        <div class="nxp-setting">
            <span>{{ __("Rate limiting", "nxp-easy-forms") }}</span>
            <div class="nxp-setting__inline">
                <label>
                    <span>{{ __("Max submissions", "nxp-easy-forms") }}</span>
                    <input
                        type="number"
                        min="1"
                        v-model.number="local.throttle.max_requests"
                    />
                </label>
                <label>
                    <span>{{ __("Per seconds", "nxp-easy-forms") }}</span>
                    <input
                        type="number"
                        min="1"
                        v-model.number="local.throttle.per_seconds"
                    />
                </label>
            </div>
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
