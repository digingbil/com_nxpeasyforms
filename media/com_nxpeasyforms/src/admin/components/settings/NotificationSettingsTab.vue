<template>
    <div
        class="nxp-modal__panel"
        role="tabpanel"
        aria-labelledby="nxp-tab-notifications"
        id="nxp-panel-notifications"
    >
        <div class="nxp-notification__primary-toggle">
            <label class="nxp-setting nxp-setting--switch">
                <span>{{ __("Send email notifications") }}</span>
                <input type="checkbox" v-model="local.send_email" />
            </label>
        </div>

        <div v-if="local.send_email" class="nxp-setting-group">

            <div class="nxp-email-global-controls nxp-setting-group">
                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Use global email delivery settings") }}</span>
                    <input type="checkbox" v-model="local.use_global_email_delivery" />
                </label>

                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Use global Recipient email") }}</span>
                    <input type="checkbox" v-model="local.use_global_recipient" />
                </label>
                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Use global From email") }}</span>
                    <input type="checkbox" v-model="local.use_global_from_email" />
                </label>
                <label class="nxp-setting nxp-setting--switch">
                    <span>{{ __("Use global From name") }}</span>
                    <input type="checkbox" v-model="local.use_global_from_name" />
                </label>
            </div>

            <label class="nxp-setting" :class="{ 'nxp-setting--dim': local.use_global_recipient }">
                <span>{{ __("Recipient email") }}</span>
                <input type="email" v-model="local.email_recipient" :disabled="local.use_global_recipient" />
                <small v-if="local.use_global_recipient" class="nxp-setting__hint">{{ __("Using the global default recipient from Settings.") }}</small>
            </label>
            <label class="nxp-setting">
                <span>{{ __("Email subject") }}</span>
                <input type="text" v-model="local.email_subject" />
            </label>
            <label class="nxp-setting" :class="{ 'nxp-setting--dim': local.use_global_from_name }">
                <span>{{ __("From name") }}</span>
                <input type="text" v-model="local.email_from_name" :disabled="local.use_global_from_name" />
            </label>
            <label class="nxp-setting" :class="{ 'nxp-setting--dim': local.use_global_from_email }">
                <span>{{ __("From email") }}</span>
                <input type="email" v-model="local.email_from_address" :disabled="local.use_global_from_email" />
                <small class="nxp-setting__hint">
                    {{
                        __(
                            "Use an address on the same domain as your site (for example, noreply@yourdomain.com) and publish SPF and DKIM DNS records so messages avoid spam filters. Especially important if you rely on the default Joomla mailer.",
                            "nxp-easy-forms",
                        )
                    }}
                </small>
            </label>
            <button
                type="button"
                class="button button-secondary nxp-notification__test-email"
                @click="requestTestEmail"
                :disabled="testEmailLoading"
            >
                <span class="fa-regular fa-paper-plane" aria-hidden="true"></span>
                <span v-if="testEmailLoading">
                    {{ __("Sending…", "nxp-easy-forms") }}
                </span>
                <span v-else>
                    {{ __("Send test email") }}
                </span>
            </button>
            <p
                v-if="testEmailFeedback.message"
                class="nxp-notification__test-email-feedback"
                :class="`nxp-notification__test-email-feedback--${testEmailFeedback.type}`"
                role="alert"
            >
                {{ testEmailFeedback.message }}
            </p>

            <div class="nxp-setting" v-if="!local.use_global_email_delivery">
                <span>{{ __("Delivery method", "nxp-easy-forms") }}</span>
                <select v-model="local.email_delivery.provider">
                    <option value="joomla">
                        {{ __("Joomla default (Factory::getMailer())", "nxp-easy-forms") }}
                    </option>
                    <option value="sendgrid">
                        {{ __("SendGrid API", "nxp-easy-forms") }}
                    </option>
                        <option value="mailgun">
                            {{ __("Mailgun API", "nxp-easy-forms") }}
                        </option>
                        <option value="postmark">
                            {{ __("Postmark API", "nxp-easy-forms") }}
                        </option>
                        <option value="brevo">
                            {{ __("Brevo API", "nxp-easy-forms") }}
                        </option>
                        <option value="amazon_ses">
                            {{ __("Amazon SES", "nxp-easy-forms") }}
                        </option>
                    <option value="smtp2go">
                        {{ __("SMTP2GO API", "nxp-easy-forms") }}
                    </option>
                    <option value="smtp">
                        {{ __("Custom SMTP", "nxp-easy-forms") }}
                    </option>
                    <option value="mailpit">
                        {{
                            __(
                                "Mailpit (SMTP - local testing only)",
                                "nxp-easy-forms",
                            )
                        }}
                    </option>
                </select>
            </div>
            <small
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'joomla'"
                class="nxp-setting__hint"
            >
                {{
                    __(
                        "The Joomla mailer sends through your server. DNS records (SPF, DKIM, DMARC) for your domain greatly improve deliverability. Switch to SMTP or an email API if you need guaranteed delivery.",
                        "nxp-easy-forms",
                    )
                }}
            </small>

            <small v-else-if="local.use_global_email_delivery" class="nxp-setting__hint">
                {{ __("This form will use the plugin's global email delivery provider and credentials (configured under Settings).") }}
            </small>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'sendgrid'"
                class="nxp-setting nxp-setting--stacked"
            >
                <span>{{ __("SendGrid API key", "nxp-easy-forms") }}</span>
                <input
                    type="text"
                    v-model="local.email_delivery.sendgrid.api_key"
                    autocomplete="off"
                />
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'mailgun'"
                class="nxp-setting-group"
            >
                <label class="nxp-setting">
                    <span>{{ __("Mailgun API key", "nxp-easy-forms") }}</span>
                    <input
                        type="text"
                        v-model="local.email_delivery.mailgun.api_key"
                        autocomplete="off"
                    />
                </label>
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Domain", "nxp-easy-forms") }}</span>
                        <input type="text" v-model="local.email_delivery.mailgun.domain" />
                    </label>
                    <label>
                        <span>{{ __("Region", "nxp-easy-forms") }}</span>
                        <select v-model="local.email_delivery.mailgun.region">
                            <option value="us">US</option>
                            <option value="eu">EU</option>
                        </select>
                    </label>
                </div>
                <small class="nxp-setting__hint">
                    {{ __("Use your Mailgun sending domain (e.g. mg.example.com).", "nxp-easy-forms") }}
                </small>
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'postmark'"
                class="nxp-setting nxp-setting--stacked"
            >
                <span>{{ __("Postmark Server API Token", "nxp-easy-forms") }}</span>
                <input
                    type="text"
                    v-model="local.email_delivery.postmark.api_token"
                    autocomplete="off"
                />
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'brevo'"
                class="nxp-setting nxp-setting--stacked"
            >
                <span>{{ __("Brevo API key", "nxp-easy-forms") }}</span>
                <input
                    type="text"
                    v-model="local.email_delivery.brevo.api_key"
                    autocomplete="off"
                />
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'amazon_ses'"
                class="nxp-setting-group"
            >
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Access key", "nxp-easy-forms") }}</span>
                        <input
                            type="text"
                            v-model="local.email_delivery.amazon_ses.access_key"
                            autocomplete="off"
                        />
                    </label>
                    <label>
                        <span>{{ __("Secret key", "nxp-easy-forms") }}</span>
                        <input
                            type="text"
                            v-model="local.email_delivery.amazon_ses.secret_key"
                            autocomplete="off"
                        />
                    </label>
                </div>
                <label class="nxp-setting">
                    <span>{{ __("Region", "nxp-easy-forms") }}</span>
                    <input
                        type="text"
                        placeholder="us-east-1"
                        v-model="local.email_delivery.amazon_ses.region"
                    />
                </label>
                <small class="nxp-setting__hint">
                    {{ __("Ensure your sender identity is verified in SES and the region matches your SES settings.", "nxp-easy-forms") }}
                </small>
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'mailpit'"
                class="nxp-setting nxp-setting--split"
            >
                <label>
                    <span>{{ __("Mailpit host", "nxp-easy-forms") }}</span>
                    <input
                        type="text"
                        v-model="local.email_delivery.mailpit.host"
                    />
                </label>
                <label>
                    <span>{{ __("Mailpit port", "nxp-easy-forms") }}</span>
                    <input
                        type="number"
                        min="1"
                        v-model.number="local.email_delivery.mailpit.port"
                    />
                </label>
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'smtp2go'"
                class="nxp-setting nxp-setting--stacked"
            >
                <span>{{ __("SMTP2GO API key", "nxp-easy-forms") }}</span>
                <input
                    type="text"
                    v-model="local.email_delivery.smtp2go.api_key"
                    autocomplete="off"
                />
            </div>

            <div
                v-if="!local.use_global_email_delivery && local.email_delivery.provider === 'smtp'"
                class="nxp-setting-group"
            >
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("SMTP host", "nxp-easy-forms") }}</span>
                        <input
                            type="text"
                            v-model="local.email_delivery.smtp.host"
                        />
                    </label>
                    <label>
                        <span>{{ __("Port", "nxp-easy-forms") }}</span>
                        <input
                            type="number"
                            min="1"
                            v-model.number="local.email_delivery.smtp.port"
                        />
                    </label>
                </div>
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Encryption", "nxp-easy-forms") }}</span>
                        <select v-model="local.email_delivery.smtp.encryption">
                            <option value="none">
                                {{ __("None", "nxp-easy-forms") }}
                            </option>
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                        </select>
                    </label>
                    <div></div>
                </div>
                <label class="nxp-setting">
                    <span>{{ __("Username", "nxp-easy-forms") }}</span>
                    <input
                        type="text"
                        v-model="local.email_delivery.smtp.username"
                        autocomplete="off"
                    />
                </label>
                <label class="nxp-setting">
                    <span>{{ __("Password", "nxp-easy-forms") }}</span>
                    <input
                        type="password"
                        v-model="local.email_delivery.smtp.password"
                        :placeholder="
                            local.email_delivery.smtp.password_set
                                ? '••••••'
                                : ''
                        "
                        autocomplete="new-password"
                    />
                    <small
                        v-if="local.email_delivery.smtp.password_set"
                        class="nxp-setting__hint"
                    >
                        {{
                            __(
                                "Existing password is stored securely. Enter a new value to replace it.",
                                "nxp-easy-forms",
                            )
                        }}
                    </small>
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

const { local, requestTestEmail, testEmailFeedback, testEmailLoading } = ctx;
</script>

<style scoped>
/* Dim the label text when the corresponding input is disabled via global settings */
.nxp-setting--dim > span:first-child {
    color: #abb1b7;
}

/* Stack the global email toggles cleanly, one per line */
.nxp-email-global-controls {
    display: grid;
    gap: 10px;
}

.nxp-notification__primary-toggle {
    padding: 12px 16px;
    margin-bottom: 20px;
    border: 1px solid var(--nxp-surface-border);
    border-radius: 10px;
    background: var(--nxp-panel-bg);
    box-shadow: 0 1px 2px var(--nxp-drawer-shadow);
}

.nxp-notification__primary-toggle .nxp-setting {
    margin: 0;
}

.nxp-notification__test-email {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 6px;
    line-height: 1.4;
}

.nxp-notification__test-email .fa-regular,
.nxp-notification__test-email .fa-solid {
    font-size: 16px;
}

.nxp-notification__test-email-feedback {
    margin-top: 8px;
    font-size: 0.95rem;
}

.nxp-notification__test-email-feedback--success {
    color: var(--bs-success, #198754);
}

.nxp-notification__test-email-feedback--error {
    color: var(--bs-danger, #b32d2e);
}
</style>
