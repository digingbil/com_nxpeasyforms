<template>
    <div class="nxp-settings">
        <div class="card" style="max-width: 860px;">
            <h2>{{ __("Email delivery") }}</h2>

            <div class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("Default From name") }}</span>
                    <input type="text" v-model="form.from_name" />
                </label>
            </div>
            <div class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("Default From email") }}</span>
                    <input type="email" v-model="form.from_email" />
                </label>
                <small class="nxp-setting__hint">{{ fromHint }}</small>
            </div>

            <div class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("Default recipient email") }}</span>
                    <input type="email" v-model="form.recipient" />
                </label>
                <small class="nxp-setting__hint">{{ __("Used when a form does not specify its own recipient.") }}</small>
            </div>

            <div class="nxp-setting">
                <span>{{ __("Delivery method") }}</span>
                <select v-model="form.delivery.provider">
                    <option value="joomla">{{ __("Joomla default (Factory::getMailer())") }}</option>
                    <option value="sendgrid">{{ __("SendGrid API") }}</option>
                    <option value="mailgun">{{ __("Mailgun API") }}</option>
                    <option value="postmark">{{ __("Postmark API") }}</option>
                    <option value="brevo">{{ __("Brevo API") }}</option>
                    <option value="amazon_ses">{{ __("Amazon SES") }}</option>
                    <option value="smtp2go">{{ __("SMTP2GO API") }}</option>
                    <option value="smtp">{{ __("Custom SMTP") }}</option>
                    <option value="mailpit">{{ __("Mailpit (SMTP - local testing only)") }}</option>
                </select>
            </div>

            <div v-if="form.delivery.provider === 'sendgrid'" class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("SendGrid API key") }}</span>
                    <input type="text" v-model="form.delivery.sendgrid.api_key" autocomplete="off" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'mailgun'" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Domain") }}</span>
                        <input type="text" v-model="form.delivery.mailgun.domain" />
                    </label>
                    <label>
                        <span>{{ __("Region") }}</span>
                        <select v-model="form.delivery.mailgun.region">
                            <option value="us">US</option>
                            <option value="eu">EU</option>
                        </select>
                    </label>
                </div>
                <label class="nxp-setting">
                    <span>{{ __("Mailgun API key") }}</span>
                    <input type="text" v-model="form.delivery.mailgun.api_key" autocomplete="off" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'postmark'" class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("Postmark Server API Token") }}</span>
                    <input type="text" v-model="form.delivery.postmark.api_token" autocomplete="off" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'brevo'" class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("Brevo API key") }}</span>
                    <input type="text" v-model="form.delivery.brevo.api_key" autocomplete="off" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'amazon_ses'" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Access key") }}</span>
                        <input type="text" v-model="form.delivery.amazon_ses.access_key" autocomplete="off" />
                    </label>
                    <label>
                        <span>{{ __("Secret key") }}</span>
                        <input type="text" v-model="form.delivery.amazon_ses.secret_key" autocomplete="off" />
                    </label>
                </div>
                <label class="nxp-setting">
                    <span>{{ __("Region") }}</span>
                    <input type="text" placeholder="us-east-1" v-model="form.delivery.amazon_ses.region" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'smtp2go'" class="nxp-setting nxp-setting--stacked">
                <label>
                    <span>{{ __("SMTP2GO API key") }}</span>
                    <input type="text" v-model="form.delivery.smtp2go.api_key" autocomplete="off" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'mailpit'" class="nxp-setting nxp-setting--split">
                <label>
                    <span>{{ __("Mailpit host") }}</span>
                    <input type="text" v-model="form.delivery.mailpit.host" />
                </label>
                <label>
                    <span>{{ __("Mailpit port") }}</span>
                    <input type="number" min="1" v-model.number="form.delivery.mailpit.port" />
                </label>
            </div>

            <div v-if="form.delivery.provider === 'smtp'" class="nxp-setting-group">
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("SMTP host") }}</span>
                        <input type="text" v-model="form.delivery.smtp.host" />
                    </label>
                    <label>
                        <span>{{ __("Port") }}</span>
                        <input type="number" min="1" v-model.number="form.delivery.smtp.port" />
                    </label>
                </div>
                <div class="nxp-setting nxp-setting--split">
                    <label>
                        <span>{{ __("Encryption") }}</span>
                        <select v-model="form.delivery.smtp.encryption">
                            <option value="none">{{ __("None") }}</option>
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                        </select>
                    </label>
                    <div></div>
                </div>
                <label class="nxp-setting">
                    <span>{{ __("Username") }}</span>
                    <input type="text" v-model="form.delivery.smtp.username" autocomplete="off" />
                </label>
                <label class="nxp-setting">
                    <span>{{ __("Password") }}</span>
                    <input type="password" v-model="form.delivery.smtp.password" :placeholder="form.delivery.smtp.password_set ? '••••••' : ''" autocomplete="new-password" />
                    <small v-if="form.delivery.smtp.password_set" class="nxp-setting__hint">{{ __("Existing password is stored securely. Enter a new value to replace it.") }}</small>
                </label>
            </div>

            <div style="display:flex; gap:.5rem; align-items:center; margin-top: 16px;">
                <button type="button" class="button button-primary" @click="save" :disabled="saving">{{ saving ? __("Saving…") : __("Save settings") }}</button>
                <div class="nxp-setting" style="margin-left:auto; display:flex; gap:.5rem; align-items:center;">
                    <input type="email" v-model="testRecipient" placeholder="name@example.com" style="width: 260px;" />
                    <button type="button" class="button" @click="sendTest" :disabled="testing">{{ testing ? __("Sending…") : __("Send test") }}</button>
                </div>
            </div>

            <p v-if="message" :class="{'notice': true, 'notice-success': messageType==='success', 'notice-error': messageType==='error'}" style="margin-top:12px; padding:.5rem .75rem;">
                {{ message }}
            </p>
        </div>

        <div class="card" style="max-width: 860px; margin-top: 16px;">
            <div style="display:flex; align-items:center; justify-content: space-between; gap: 12px;">
                <h2 style="margin: 0;">{{ __("Diagnostics (wp_mail only)") }}</h2>
                <button type="button" class="button" @click="loadDiagnostics">{{ __("View diagnostics") }}</button>
            </div>
            <div v-if="diag.loaded" style="margin-top: 12px;">
                <div v-if="diag.warningAt" class="notice notice-warning" style="padding:.5rem .75rem;">
                    {{ __("A recent email attempt reported a problem.") }}
                    <small style="display:block; color:#6c757d;">{{ __("Recorded at:") }} {{ new Date(diag.warningAt*1000).toLocaleString() }}</small>
                </div>

                <div class="nxp-diagnostic-block">
                    <h3 style="margin-top: 10px;">{{ __("Joomla mail") }}</h3>
                    <div v-if="diag.wpMail.lastError" class="notice notice-error" style="padding:.5rem .75rem;">
                        <strong>{{ __("Last error:") }}</strong>
                        <div>{{ diag.wpMail.lastError.message }}</div>
                        <small v-if="diag.wpMail.lastError.recorded_at" style="display:block; color:#6c757d;">{{ __("Recorded at:") }} {{ new Date(diag.wpMail.lastError.recorded_at*1000).toLocaleString() }}</small>
                        <details v-if="diag.wpMail.lastError.details" style="margin-top:6px;">
                            <summary>{{ __("Details") }}</summary>
                            <pre style="white-space: pre-wrap;">{{ JSON.stringify(diag.wpMail.lastError.details, null, 2) }}</pre>
                        </details>
                    </div>
                    <div v-else class="notice notice-success" style="padding:.5rem .75rem;">
                        {{ __("No wp_mail errors recorded recently.") }}
                    </div>

                    <div v-if="diag.wpMail.lastSuccess" class="notice" style="padding:.5rem .75rem;">
                        <strong>{{ __("Last success:") }}</strong>
                        <div>
                            <small>{{ __("To:") }} {{ diag.wpMail.lastSuccess.to }}</small>
                            <br />
                            <small>{{ __("Subject:") }} {{ diag.wpMail.lastSuccess.subject }}</small>
                            <br />
                            <small v-if="diag.wpMail.lastSuccess.recorded_at">{{ __("Recorded at:") }} {{ new Date(diag.wpMail.lastSuccess.recorded_at*1000).toLocaleString() }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import { apiFetch } from "@/admin/utils/http";
import { __ } from "@/utils/i18n";

const api = window.nxpEasyFormsSettings || { restUrl: "", nonce: "", defaults: {} };

const form = reactive({
    from_name: api.defaults?.from_name || "",
    from_email: api.defaults?.from_email || "",
    recipient: api.defaults?.recipient || "",
    delivery: {
        provider: "joomla",
        sendgrid: { api_key: "" },
        mailgun: { api_key: "", domain: "", region: "us" },
        postmark: { api_token: "" },
        brevo: { api_key: "" },
        amazon_ses: { access_key: "", secret_key: "", region: "us-east-1" },
        mailpit: { host: "127.0.0.1", port: 1025 },
        smtp2go: { api_key: "" },
        smtp: { host: "", port: 587, encryption: "tls", username: "", password: "", password_set: false },
    },
});

const saving = ref(false);
const testing = ref(false);
const message = ref("");
const messageType = ref("success");
const testRecipient = ref("");

const diag = reactive({ loaded: false, warningAt: null, wpMail: { lastError: null, lastSuccess: null } });

const fromHint = __(
    "Use an address on the same domain as your site and publish SPF/DKIM DNS records to improve deliverability.",
);

async function load() {
    if (!api.restUrl) return;
    try {
        const res = await apiFetch('settings/email', {}, {
            nonce: api.nonce,
            base: api.restUrl,
        });
        const data = await res.json();
        if (data && data.success && data.settings) {
            Object.assign(form, data.settings);
        }
    } catch (e) {
        // noop
    }
}

async function save() {
    if (!api.restUrl) return;
    saving.value = true;
    message.value = "";
    try {
        const res = await apiFetch('settings/email/save', {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(form),
        }, {
            nonce: api.nonce,
            base: api.restUrl,
        });
        const data = await res.json();
        if (res.ok && data.success) {
            messageType.value = "success";
            message.value = __("Settings saved.");
            await load(); // reload to refresh password_set flag
        } else {
            messageType.value = "error";
            message.value = data?.message || __("Failed to save settings.");
        }
    } catch (e) {
        messageType.value = "error";
        message.value = __("Failed to save settings.");
    } finally {
        saving.value = false;
    }
}

async function sendTest() {
    if (!api.restUrl) return;
    if (!testRecipient.value) {
        messageType.value = "error";
        message.value = __("Enter a test recipient email.");
        return;
    }
    testing.value = true;
    message.value = "";
    try {
        const res = await apiFetch('settings/email/test', {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ recipient: testRecipient.value }),
        }, {
            nonce: api.nonce,
            base: api.restUrl,
        });
        const data = await res.json();
        if (res.ok && data.success) {
            messageType.value = "success";
            message.value = data.message || __("Test email sent.");
        } else {
            messageType.value = "error";
            message.value = data?.message || __("Failed to send test email.");
        }
    } catch (e) {
        messageType.value = "error";
        message.value = __("Failed to send test email.");
    } finally {
        testing.value = false;
    }
}

onMounted(load);

async function loadDiagnostics() {
    diag.loaded = false;
    try {
        const res = await apiFetch('settings/email/diagnostics', {}, {
            nonce: api.nonce,
            base: api.restUrl,
        });
        const data = await res.json();
        if (res.ok && data.success) {
            Object.assign(diag, { loaded: true, ...data.diagnostics });
        } else {
            Object.assign(diag, { loaded: true, warningAt: null, wpMail: { lastError: null, lastSuccess: null } });
        }
    } catch (e) {
        Object.assign(diag, { loaded: true, warningAt: null, wpMail: { lastError: null, lastSuccess: null } });
    }
}
</script>

<style scoped>
.nxp-setting {
    margin: 10px 0;
}
.nxp-setting--stacked label,
.nxp-setting label {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.nxp-setting--split {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.nxp-setting__hint {
    color: #6c757d;
}
</style>
