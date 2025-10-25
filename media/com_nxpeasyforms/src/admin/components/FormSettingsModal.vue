<template>
    <div
        class="nxp-modal"
        :class="{ 'nxp-modal--visible': visible }"
        role="dialog"
        aria-modal="true"
    >
        <div class="nxp-modal__overlay" @click="emit('close')"></div>
        <div class="nxp-modal__content">
            <header class="nxp-modal__header">
                <h2>{{ __("Form settings", "nxp-easy-forms") }}</h2>
                <div class="nxp-settings-mode-toggle">
                    <button
                        type="button"
                        class="button button-small"
                        :class="{ 'is-primary': settingsMode === 'simple' }"
                        @click="settingsMode = 'simple'"
                    >
                        {{ __("Essential", "nxp-easy-forms") }}
                    </button>
                    <button
                        type="button"
                        class="button button-small"
                        :class="{ 'is-primary': settingsMode === 'advanced' }"
                        @click="settingsMode = 'advanced'"
                    >
                        {{ __("All Options", "nxp-easy-forms") }}
                    </button>
                </div>
                <button
                    type="button"
                    class="button button-link nxp-modal-close"
                    @click="emit('close')"
                >
                    <img
                        :src="ICON_CLOSE"
                        alt=""
                        aria-hidden="true"
                        class="nxp-modal-close__icon"
                    />
                    <span class="screen-reader-text">{{ __("Close", "nxp-easy-forms") }}</span>
                </button>
            </header>
            <section class="nxp-modal__body">
                <nav class="nxp-modal__tabs" role="tablist">
                    <button
                        v-for="tab in visibleTabs"
                        :key="tab.id"
                        type="button"
                        class="nxp-tab"
                        role="tab"
                        :id="`nxp-tab-${tab.id}`"
                        :class="{ 'nxp-tab--active': activeTab === tab.id }"
                        :aria-selected="activeTab === tab.id"
                        :aria-controls="`nxp-panel-${tab.id}`"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
                <div class="nxp-modal__panels">
                    <GeneralSettingsTab v-show="activeTab === 'general'" />
                    <NotificationSettingsTab v-show="activeTab === 'notifications'" />
                    <JoomlaIntegrationsSettingsTab v-show="activeTab === 'joomla'" />
                    <IntegrationsSettingsTab v-show="activeTab === 'integrations'" />
                    <SecuritySettingsTab v-show="activeTab === 'security'" />
                    <PrivacySettingsTab v-show="activeTab === 'privacy'" />
                    <AdvancedSettingsTab v-show="activeTab === 'advanced'" />
                </div>
            </section>
            <footer class="nxp-modal__footer">
                <button
                    type="button"
                    class="button button-secondary"
                    @click="emit('close')"
                >
                    {{ __("Cancel", "nxp-easy-forms") }}
                </button>
                <button
                    type="button"
                    class="button button-primary"
                    @click="save"
                >
                    {{ __("Save settings", "nxp-easy-forms") }}
                </button>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch, computed, provide } from "vue";
import { __ } from "@/utils/i18n";
import { useFormStore } from "@/admin/stores/formStore";
import { createFormDefaults } from "@/admin/utils/formDefaults";
import { safeTrim, isObject, createRowId } from "@/admin/utils/strings";
import { useFormSettings } from "@/admin/composables/useFormSettings";
import GeneralSettingsTab from "./settings/GeneralSettingsTab.vue";
import NotificationSettingsTab from "./settings/NotificationSettingsTab.vue";
import JoomlaIntegrationsSettingsTab from "./settings/JoomlaIntegrationsSettingsTab.vue";
import IntegrationsSettingsTab from "./settings/IntegrationsSettingsTab.vue";
import SecuritySettingsTab from "./settings/SecuritySettingsTab.vue";
import PrivacySettingsTab from "./settings/PrivacySettingsTab.vue";
import AdvancedSettingsTab from "./settings/AdvancedSettingsTab.vue";
import ICON_CLOSE from "../../../assets/icons/hexagon-letter-x.svg";

const tabs = [
    { id: "general", label: __("General", "nxp-easy-forms") },
    { id: "notifications", label: __("Email Settings", "nxp-easy-forms") },
    { id: "joomla", label: __("Joomla", "nxp-easy-forms") },
    { id: "integrations", label: __("Integrations", "nxp-easy-forms") },
    { id: "security", label: __("Security", "nxp-easy-forms") },
    { id: "privacy", label: __("Privacy", "nxp-easy-forms") },
    { id: "advanced", label: __("Advanced", "nxp-easy-forms") },
];

const SIMPLE_MODE_TABS = ["general", "notifications"];
const SETTINGS_MODE_KEY = "nxp_form_settings_mode";

const getStoredSettingsMode = () => {
    try {
        const stored = localStorage.getItem(SETTINGS_MODE_KEY);
        return stored === "advanced" ? "advanced" : "simple";
    } catch {
        return "simple";
    }
};

const settingsMode = ref(getStoredSettingsMode());

const visibleTabs = computed(() => {
    if (settingsMode.value === "simple") {
        return tabs.filter((tab) => SIMPLE_MODE_TABS.includes(tab.id));
    }
    return tabs;
});

const activeTab = ref(tabs[0].id);

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    options: {
        type: Object,
        required: true,
    },
    fields: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update", "close", "test-email"]);

const store = useFormStore();
const builderSettings = window.nxpEasyForms?.builder || {};

const local = reactive(createFormDefaults());

const {
    mailchimpAudiences,
    mailchimpAudiencesLoading,
    mailchimpAudiencesError,
    fetchMailchimpAudiences: fetchMailchimpAudiencesBase,
    resetMailchimpAudiences,
} = useFormSettings();

const slackTemplatePlaceholder = __(
    "New submission on {{form_title}}\nEmail: {{field:email}}",
    "nxp-easy-forms"
);
const slackTemplateHint = __(
    "Use placeholders such as {{form_title}} or {{field:email}}. Leave blank to send the default summary.",
    "nxp-easy-forms"
);
const teamsTemplatePlaceholder = __(
    "**Form**: {{form_title}}\n**Message**: {{field:message}}",
    "nxp-easy-forms"
);
const teamsTemplateHint = __(
    "Supports basic Markdown. Placeholders such as {{form_title}} and {{field:message}} are replaced automatically.",
    "nxp-easy-forms"
);

const fieldOptions = computed(() =>
    (props.fields || [])
        .filter((field) => field && field.name)
        .map((field) => ({
            value: field.name,
            label: field.label || field.name,
            type: field.type || "text",
        }))
);

const fileFieldOptions = computed(() =>
    (props.fields || [])
        .filter((field) => field && field.type === "file")
        .map((field) => ({
            value: field.name,
            label: field.label || field.name,
        }))
);

const emailFieldOptions = computed(() =>
    fieldOptions.value.filter((option) => option.type === "email")
);

const mappableFieldOptions = computed(() => fieldOptions.value);

const addSalesforceMapping = () => {
    local.integrations.salesforce.mappings.push({
        id: createRowId(),
        salesforce_field: "",
        form_field: "",
    });
};

const removeSalesforceMapping = (id) => {
    const index = local.integrations.salesforce.mappings.findIndex(
        (mapping) => mapping.id === id
    );
    if (index !== -1) {
        local.integrations.salesforce.mappings.splice(index, 1);
    }
};

const addHubspotMapping = () => {
    local.integrations.hubspot.field_mappings.push({
        id: createRowId(),
        hubspot_field: "",
        form_field: "",
    });
};

const removeHubspotMapping = (id) => {
    const index = local.integrations.hubspot.field_mappings.findIndex(
        (mapping) => mapping.id === id
    );
    if (index !== -1) {
        local.integrations.hubspot.field_mappings.splice(index, 1);
    }
};

const mapOptionsToLocal = (value) => {
    if (!value) {
        return;
    }

    resetMailchimpAudiences();

    local.store_submissions = value.store_submissions !== false;
    local.send_email = value.send_email !== false;
    // Global usage flags (default to true). Map legacy use_global_sender if present.
    const legacySender = value.use_global_sender;
    local.use_global_email_delivery = value.use_global_email_delivery !== false;
    local.use_global_recipient = value.use_global_recipient === false ? false : true;
    if (typeof value.use_global_from_name === 'boolean') {
        local.use_global_from_name = value.use_global_from_name;
    } else if (typeof legacySender === 'boolean') {
        local.use_global_from_name = legacySender;
    } else {
        local.use_global_from_name = true;
    }
    if (typeof value.use_global_from_email === 'boolean') {
        local.use_global_from_email = value.use_global_from_email;
    } else if (typeof legacySender === 'boolean') {
        local.use_global_from_email = legacySender;
    } else {
        local.use_global_from_email = true;
    }
    local.email_recipient = value.email_recipient || "";
    local.email_subject = value.email_subject || "";
    local.email_from_name = value.email_from_name || "";
    local.email_from_address = value.email_from_address || "";
    local.honeypot = value.honeypot !== false;
    local.success_message =
        value.success_message || local.success_message;
    local.error_message = value.error_message || local.error_message;
    local.ip_storage = value.ip_storage || "anonymous";
    local.delete_submissions_after_days_enabled =
        value.delete_submissions_after_days_enabled === true;
    local.delete_submissions_after_days =
        Number(value.delete_submissions_after_days ?? 90) || 90;

    local.throttle = {
        max_requests: Number(value.throttle?.max_requests ?? 3) || 1,
        per_seconds: Number(value.throttle?.per_seconds ?? 10) || 1,
    };

    local.captcha = {
        provider: value.captcha?.provider || "none",
        site_key: value.captcha?.site_key || "",
        secret_key: value.captcha?.secret_key || "",
    };

    local.email_delivery.provider =
        value.email_delivery?.provider || "joomla";
    local.email_delivery.sendgrid.api_key =
        value.email_delivery?.sendgrid?.api_key || "";
    // New providers
    local.email_delivery.mailgun = {
        api_key: value.email_delivery?.mailgun?.api_key || "",
        domain: value.email_delivery?.mailgun?.domain || "",
        region: value.email_delivery?.mailgun?.region || "us",
    };
    local.email_delivery.postmark = {
        api_token: value.email_delivery?.postmark?.api_token || "",
    };
    local.email_delivery.brevo = {
        api_key: value.email_delivery?.brevo?.api_key || "",
    };
    local.email_delivery.amazon_ses = {
        access_key: value.email_delivery?.amazon_ses?.access_key || "",
        secret_key: value.email_delivery?.amazon_ses?.secret_key || "",
        region: value.email_delivery?.amazon_ses?.region || "us-east-1",
    };
    local.email_delivery.mailpit.host =
        value.email_delivery?.mailpit?.host || "127.0.0.1";
    local.email_delivery.mailpit.port =
        Number(value.email_delivery?.mailpit?.port ?? 1025) || 1025;
    local.email_delivery.smtp2go.api_key =
        value.email_delivery?.smtp2go?.api_key || "";
    local.email_delivery.smtp.host = value.email_delivery?.smtp?.host || "";
    local.email_delivery.smtp.port =
        Number(value.email_delivery?.smtp?.port ?? 587) || 587;
    local.email_delivery.smtp.encryption =
        value.email_delivery?.smtp?.encryption || "tls";
    local.email_delivery.smtp.username =
        value.email_delivery?.smtp?.username || "";
    local.email_delivery.smtp.password =
        value.email_delivery?.smtp?.password || "";
    local.email_delivery.smtp.password_set =
        value.email_delivery?.smtp?.password_set === true;

    local.custom_css = value.custom_css || "";

    local.webhooks = {
        enabled: value.webhooks?.enabled === true,
        endpoint: value.webhooks?.endpoint || "",
        secret: "",
        secret_set: value.webhooks?.secret_set === true,
        remove_secret: false,
    };

    const integrations = value.integrations || {};
    const defaults = createFormDefaults().integrations;

    const articleSource =
        integrations?.joomla_article ||
        {};
    const articleMap = isObject(articleSource.map) ? articleSource.map : {};

    local.integrations.joomla_article = {
        ...defaults.joomla_article,
        enabled: articleSource.enabled === true,
        category_id: Number(articleSource.category_id ?? articleSource.post_type ?? 0) || 0,
        status:
            articleSource.status ||
            articleSource.post_status ||
            "unpublished",
        author_mode: articleSource.author_mode || "current_user",
        fixed_author_id: Number(articleSource.fixed_author_id ?? 0) || 0,
        language: articleSource.language || "*",
        access: Number(articleSource.access ?? 1) || 1,
        map: {
            title: safeTrim(articleMap.title || ""),
            introtext: safeTrim(
                articleMap.introtext || articleMap.content || ""
            ),
            fulltext: safeTrim(articleMap.fulltext || ""),
            tags: safeTrim(articleMap.tags || ""),
            alias: safeTrim(articleMap.alias || ""),
            meta_description: safeTrim(
                articleMap.meta_description || articleMap.excerpt || ""
            ),
            meta_keywords: safeTrim(articleMap.meta_keywords || ""),
        },
    };

    const mailchimpTags = Array.isArray(integrations?.mailchimp?.tags)
        ? integrations.mailchimp.tags
        : [];

    local.integrations.mailchimp = {
        ...defaults.mailchimp,
        enabled: integrations?.mailchimp?.enabled === true,
        api_key: "",
        api_key_set: integrations?.mailchimp?.api_key_set === true,
        remove_api_key: false,
        list_id: integrations?.mailchimp?.list_id || "",
        double_opt_in: integrations?.mailchimp?.double_opt_in === true,
        email_field: integrations?.mailchimp?.email_field || "",
        first_name_field: integrations?.mailchimp?.first_name_field || "",
        last_name_field: integrations?.mailchimp?.last_name_field || "",
        tags_input: mailchimpTags.join(", "),
    };

    local.integrations.zapier = {
        ...defaults.zapier,
        enabled: integrations?.zapier?.enabled === true,
        webhook_url: integrations?.zapier?.webhook_url || "",
    };

    local.integrations.make = {
        ...defaults.make,
        enabled: integrations?.make?.enabled === true,
        webhook_url: integrations?.make?.webhook_url || "",
    };

    local.integrations.slack = {
        ...defaults.slack,
        enabled: integrations?.slack?.enabled === true,
        webhook_url: integrations?.slack?.webhook_url || "",
        message_template: integrations?.slack?.message_template || "",
    };

    local.integrations.teams = {
        ...defaults.teams,
        enabled: integrations?.teams?.enabled === true,
        webhook_url: integrations?.teams?.webhook_url || "",
        card_title: integrations?.teams?.card_title || "",
        message_template: integrations?.teams?.message_template || "",
    };

    const salesforceMappings = Array.isArray(integrations?.salesforce?.mappings)
        ? integrations.salesforce.mappings
              .filter(isObject)
              .map((item) => ({
                  id:
                      typeof item?.id === "string" && item.id !== ""
                          ? item.id
                          : createRowId(),
                  salesforce_field: safeTrim(item?.salesforce_field || ""),
                  form_field: safeTrim(item?.form_field || ""),
              }))
        : [];

    local.integrations.salesforce = {
        ...defaults.salesforce,
        enabled: integrations?.salesforce?.enabled === true,
        org_id: integrations?.salesforce?.org_id || "",
        lead_source: integrations?.salesforce?.lead_source || "",
        assignment_rule_id:
            integrations?.salesforce?.assignment_rule_id || "",
        debug_email: integrations?.salesforce?.debug_email || "",
        mappings: salesforceMappings,
    };

const hubspotMappings = Array.isArray(
        integrations?.hubspot?.field_mappings
    )
        ? integrations.hubspot.field_mappings
              .filter(isObject)
              .map((item) => ({
                  id:
                      typeof item?.id === "string" && item.id !== ""
                          ? item.id
                          : createRowId(),
                  hubspot_field: safeTrim(item?.hubspot_field || ""),
                  form_field: safeTrim(item?.form_field || ""),
              }))
        : [];

    local.integrations.hubspot = {
        ...defaults.hubspot,
        enabled: integrations?.hubspot?.enabled === true,
        access_token: "",
        access_token_set: integrations?.hubspot?.access_token_set === true,
        remove_access_token: false,
        portal_id: integrations?.hubspot?.portal_id || "",
        form_guid: integrations?.hubspot?.form_guid || "",
        email_field: integrations?.hubspot?.email_field || "",
        field_mappings: hubspotMappings,
        legal_consent: integrations?.hubspot?.legal_consent === true,
        consent_text: integrations?.hubspot?.consent_text || "",
    };

};

watch(
    () => props.options,
    (value) => {
        mapOptionsToLocal(value);
    },
    { immediate: true }
);

watch(
    () => props.visible,
    (visible) => {
        if (!visible) {
            activeTab.value = tabs[0].id;
        }
    }
);

watch(settingsMode, (newMode) => {
    try {
        localStorage.setItem(SETTINGS_MODE_KEY, newMode);
    } catch {
        // localStorage not available
    }
    // Reset to first visible tab when switching modes
    if (visibleTabs.value.length > 0) {
        activeTab.value = visibleTabs.value[0].id;
    }
});

watch(
    emailFieldOptions,
    (options) => {
        if (options.length > 0) {
            if (!local.integrations.mailchimp.email_field) {
                local.integrations.mailchimp.email_field = options[0].value;
            }
            if (!local.integrations.hubspot.email_field) {
                local.integrations.hubspot.email_field = options[0].value;
            }
        }
    },
    { immediate: true }
);

watch(
    () => local.integrations.salesforce.enabled,
    (enabled) => {
        if (enabled && local.integrations.salesforce.mappings.length === 0) {
            addSalesforceMapping();
        }
    }
);

watch(
    () => local.integrations.hubspot.enabled,
    (enabled) => {
        if (enabled && local.integrations.hubspot.field_mappings.length === 0) {
            addHubspotMapping();
        }
    }
);

watch(
    () => local.webhooks.enabled,
    (enabled) => {
        if (!enabled) {
            local.webhooks.remove_secret = false;
            local.webhooks.secret = "";
        }
    }
);

const parseMailchimpTags = () =>
    local.integrations.mailchimp.tags_input
        .split(",")
        .map((tag) => safeTrim(tag))
        .filter((tag) => tag.length > 0);

const fetchMailchimpAudiences = async () => {
    if (!builderSettings.restUrl) {
        return;
    }

    await fetchMailchimpAudiencesBase({
        apiKey: local.integrations.mailchimp.api_key,
        formId: store.formId,
        restUrl: builderSettings.restUrl,
        nonce: builderSettings.nonce,
    });

    if (
        !local.integrations.mailchimp.list_id &&
        mailchimpAudiences.value.length > 0
    ) {
        local.integrations.mailchimp.list_id =
            mailchimpAudiences.value[0].id;
    }
};

const buildIntegrationPayload = () => ({
    zapier: {
        enabled: !!local.integrations.zapier.enabled,
        webhook_url: safeTrim(local.integrations.zapier.webhook_url),
    },
    make: {
        enabled: !!local.integrations.make.enabled,
        webhook_url: safeTrim(local.integrations.make.webhook_url),
    },
    slack: {
        enabled: !!local.integrations.slack.enabled,
        webhook_url: safeTrim(local.integrations.slack.webhook_url),
        message_template: local.integrations.slack.message_template,
    },
    teams: {
        enabled: !!local.integrations.teams.enabled,
        webhook_url: safeTrim(local.integrations.teams.webhook_url),
        card_title: safeTrim(local.integrations.teams.card_title),
        message_template: local.integrations.teams.message_template,
    },
    joomla_article: {
        enabled: !!local.integrations.joomla_article.enabled,
        category_id: Number(local.integrations.joomla_article.category_id) || 0,
        status:
            safeTrim(local.integrations.joomla_article.status) ||
            "unpublished",
        author_mode:
            local.integrations.joomla_article.author_mode || "current_user",
        fixed_author_id:
            Number(local.integrations.joomla_article.fixed_author_id) || 0,
        language:
            safeTrim(local.integrations.joomla_article.language) || "*",
        access: Number(local.integrations.joomla_article.access) || 1,
        map: {
            title: safeTrim(local.integrations.joomla_article.map.title),
            introtext: safeTrim(
                local.integrations.joomla_article.map.introtext
            ),
            fulltext: safeTrim(local.integrations.joomla_article.map.fulltext),
            tags: safeTrim(local.integrations.joomla_article.map.tags),
            alias: safeTrim(local.integrations.joomla_article.map.alias),
            meta_description: safeTrim(
                local.integrations.joomla_article.map.meta_description
            ),
            meta_keywords: safeTrim(
                local.integrations.joomla_article.map.meta_keywords
            ),
        },
    },
    mailchimp: {
        enabled: !!local.integrations.mailchimp.enabled,
        api_key: safeTrim(local.integrations.mailchimp.api_key),
        remove_api_key: !!local.integrations.mailchimp.remove_api_key,
        list_id: safeTrim(local.integrations.mailchimp.list_id),
        double_opt_in: !!local.integrations.mailchimp.double_opt_in,
        email_field: safeTrim(local.integrations.mailchimp.email_field),
        first_name_field: safeTrim(
            local.integrations.mailchimp.first_name_field
        ),
        last_name_field: safeTrim(local.integrations.mailchimp.last_name_field),
        tags: parseMailchimpTags(),
    },
    salesforce: {
        enabled: !!local.integrations.salesforce.enabled,
        org_id: safeTrim(local.integrations.salesforce.org_id),
        lead_source: safeTrim(local.integrations.salesforce.lead_source),
        assignment_rule_id: safeTrim(
            local.integrations.salesforce.assignment_rule_id
        ),
        debug_email: safeTrim(local.integrations.salesforce.debug_email),
        mappings: local.integrations.salesforce.mappings
            .map((item) => ({
                salesforce_field: safeTrim(item.salesforce_field),
                form_field: safeTrim(item.form_field),
            }))
            .filter(
                (item) => item.salesforce_field !== "" && item.form_field !== ""
            ),
    },
    hubspot: {
        enabled: !!local.integrations.hubspot.enabled,
        access_token: safeTrim(local.integrations.hubspot.access_token),
        remove_access_token: !!local.integrations.hubspot.remove_access_token,
        portal_id: safeTrim(local.integrations.hubspot.portal_id),
        form_guid: safeTrim(local.integrations.hubspot.form_guid),
        email_field: safeTrim(local.integrations.hubspot.email_field),
        field_mappings: local.integrations.hubspot.field_mappings
            .map((item) => ({
                hubspot_field: safeTrim(item.hubspot_field),
                form_field: safeTrim(item.form_field),
            }))
            .filter(
                (item) => item.hubspot_field !== "" && item.form_field !== ""
            ),
        legal_consent: !!local.integrations.hubspot.legal_consent,
        consent_text: local.integrations.hubspot.consent_text,
    },
});

const buildOptionsPayload = () => {
    const ipMode = safeTrim(local.ip_storage);
    const allowedIpModes = ["full", "anonymous", "none"];

    return {
        store_submissions: !!local.store_submissions,
        send_email: !!local.send_email,
        use_global_email_delivery: local.use_global_email_delivery !== false,
        use_global_recipient: local.use_global_recipient !== false,
        use_global_from_name: local.use_global_from_name !== false,
        use_global_from_email: local.use_global_from_email !== false,
        email_recipient: safeTrim(local.email_recipient),
        email_subject: safeTrim(local.email_subject),
        email_from_name: safeTrim(local.email_from_name),
        email_from_address: safeTrim(local.email_from_address),
        honeypot: !!local.honeypot,
        ip_storage: allowedIpModes.includes(ipMode) ? ipMode : "anonymous",
        delete_submissions_after_days_enabled:
            !!local.delete_submissions_after_days_enabled,
        delete_submissions_after_days:
            Number(local.delete_submissions_after_days) || 90,
        throttle: {
            max_requests: Number(local.throttle.max_requests) || 1,
            per_seconds: Number(local.throttle.per_seconds) || 1,
        },
        success_message: local.success_message,
        error_message: local.error_message,
        captcha: {
            provider: local.captcha.provider || "none",
            site_key: safeTrim(local.captcha.site_key),
            secret_key: safeTrim(local.captcha.secret_key),
        },
        email_delivery: {
            provider: local.email_delivery.provider || "joomla",
            sendgrid: {
                api_key: safeTrim(local.email_delivery.sendgrid.api_key),
            },
            mailgun: {
                api_key: safeTrim(local.email_delivery.mailgun?.api_key || ""),
                domain: safeTrim(local.email_delivery.mailgun?.domain || ""),
                region: safeTrim(local.email_delivery.mailgun?.region || "us"),
            },
            postmark: {
                api_token: safeTrim(local.email_delivery.postmark?.api_token || ""),
            },
            brevo: {
                api_key: safeTrim(local.email_delivery.brevo?.api_key || ""),
            },
            amazon_ses: {
                access_key: safeTrim(local.email_delivery.amazon_ses?.access_key || ""),
                secret_key: safeTrim(local.email_delivery.amazon_ses?.secret_key || ""),
                region: safeTrim(local.email_delivery.amazon_ses?.region || "us-east-1"),
            },
            mailpit: {
                host: safeTrim(local.email_delivery.mailpit.host) || "127.0.0.1",
                port: Number(local.email_delivery.mailpit.port) || 1025,
            },
            smtp2go: {
                api_key: safeTrim(local.email_delivery.smtp2go.api_key),
            },
            smtp: {
                host: safeTrim(local.email_delivery.smtp.host),
                port: Number(local.email_delivery.smtp.port) || 587,
                encryption: local.email_delivery.smtp.encryption || "tls",
                username: safeTrim(local.email_delivery.smtp.username),
                password: local.email_delivery.smtp.password,
            },
        },
        custom_css: local.custom_css || "",
        webhooks: {
            enabled: !!local.webhooks.enabled,
            endpoint: safeTrim(local.webhooks.endpoint),
            secret: local.webhooks.secret,
            remove_secret: !!local.webhooks.remove_secret,
        },
        integrations: buildIntegrationPayload(),
    };
};

const save = () => {
    emit("update", buildOptionsPayload());
    emit("close");
};

const requestTestEmail = () => {
    const recipient = safeTrim(local.email_recipient);
    if (!recipient) {
        return;
    }
    emit("test-email", {
        recipient,
        options: buildOptionsPayload(),
    });
};

provide("formSettingsContext", {
    local,
    fieldOptions,
    fileFieldOptions,
    emailFieldOptions,
    mappableFieldOptions,
    builderSettings,
    slackTemplatePlaceholder,
    slackTemplateHint,
    teamsTemplatePlaceholder,
    teamsTemplateHint,
    mailchimpAudiences,
    mailchimpAudiencesLoading,
    mailchimpAudiencesError,
    fetchMailchimpAudiences,
    requestTestEmail,
    addSalesforceMapping,
    removeSalesforceMapping,
    addHubspotMapping,
    removeHubspotMapping,
    createRowId,
});
</script>

<style>
.nxp-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 1000; }
.nxp-modal--visible { display: flex; align-items: center; justify-content: center; padding: 24px 20px; overflow-y: auto; }
.nxp-modal__overlay { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.35); }
.nxp-modal__content { position: relative; background: #fff; border-radius: 12px; width: 760px; max-width: calc(100vw - 20px); max-height: calc(100vh - 48px); display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 48px rgba(14, 30, 37, 0.12); }
.nxp-modal__header, .nxp-modal__footer { padding: 16px 20px; border-bottom: 1px solid #dcdcde; }
.nxp-modal__header { display: flex; align-items: center; gap: 16px; }
.nxp-modal__header h2 { margin: 0; flex: 1; }
.nxp-settings-mode-toggle { display: flex; gap: 4px; background: #f0f0f1; padding: 2px; border-radius: 6px;
    margin-right: 30px; }
.nxp-settings-mode-toggle .button { margin: 0!important; background: transparent!important; border: none!important; box-shadow: none!important; color: #50575e!important; font-size: 0.9rem!important; padding: 4px 12px!important; height: auto!important; line-height: 1.4!important; }
.nxp-settings-mode-toggle .button.is-primary { background: #fff!important; color: #1d2327!important; box-shadow: 0 1px 2px rgba(0,0,0,0.1)!important; }
.nxp-modal-close {
    position: absolute;
    top: 14px;
    right: 12px;
    width: 34px;
    height: 34px;
    text-align: center!important;
    border-radius: 50%!important;
    font-size: 24px!important;
    text-decoration: none!important;
    display: flex!important;
    align-items: center!important;
    justify-content: center!important;
    line-height: 32px!important;
}

.nxp-modal-close__icon {
    width: 22px;
    height: 22px;
    pointer-events: none;
}
.nxp-modal__footer { border-bottom: 0; border-top: 1px solid #dcdcde; display: flex; justify-content: flex-end; gap: 8px; background: #fff; }
.nxp-modal__body { flex: 1; display: flex; flex-direction: column; padding: 0; max-height: calc(90vh - 112px); }
.nxp-modal__tabs { display: flex; gap: 4px; padding: 12px 20px 0; border-bottom: 1px solid #dcdcde; background: #f6f7f7; }
.nxp-tab { background: transparent; border: none; padding: 0 12px 12px; font-size: 1rem; font-weight: 600; cursor: pointer; color: #50575e; border-bottom: 3px solid transparent; }
.nxp-tab:hover { color: #1d2327; }
.nxp-tab--active { color: #1d2327; border-bottom-color: #2271b1; }
.nxp-modal__panels { flex: 1; overflow-y: auto; padding: 20px; display: flex; }
.nxp-modal__panel { display: grid; gap: 16px; flex: 1; }
.nxp-setting { display: flex; flex-direction: column; gap: 6px; }
.nxp-setting label > span:first-child, .nxp-setting > span:first-child { font-size: 0.98rem; font-weight: 500; }
.nxp-setting--switch { flex-direction: row; align-items: center; justify-content: space-between; }
.nxp-setting--split { display: flex; gap: 12px; }
.nxp-setting--split label, .nxp-setting__inline label { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.nxp-setting--stacked { display: flex; flex-direction: column; gap: 6px; }
.nxp-setting-group { display: grid; gap: 16px; }
.nxp-setting__inline { display: flex; gap: 12px; margin-bottom: 10px; }
.nxp-setting__hint { color: #6c757d; font-size: 0.92rem; display: block; margin-top: 4px; }
textarea, input:not([type="checkbox"]):not([type="radio"]), select { width: 100%; font-size: 1rem; padding: 6px 8px; }
textarea { min-height: 100px; }
.nxp-integration-card { border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; background: #f9f9f9; display: grid; gap: 16px; }
.nxp-setting.nxp-setting--switch { justify-content: flex-start; }
.nxp-integration-header { display: flex; gap: 12px; align-items: flex-start; }
.nxp-integration-icon { font-size: 32px; line-height: 1; }
.nxp-integration-header h3 { margin: 0 0 4px; font-size: 1.1rem; font-weight: 600; }
.nxp-integration-description { margin: 0; font-size: 0.92rem; color: #50575e; }
.nxp-integration-hint { display: block; margin-top: 4px; color: #6c757d; font-size: 0.92rem; }
.nxp-integration-inline-error { margin-top: 6px; color: #b32d2e; font-size: 0.92rem; }
.nxp-integration-mappings { display: flex; flex-direction: column; gap: 10px; }
.nxp-integration-mapping__row {
    display: grid;
    /* two flexible columns for main selects, a medium column for mode select, and a compact button */
    grid-template-columns: minmax(200px, 1fr) minmax(200px, 1fr) minmax(120px, 180px) 44px;
    gap: 8px;
    align-items: center;
}

/* allow inputs/selects inside the grid to shrink below their content width */
.nxp-integration-mapping__row input,
.nxp-integration-mapping__row select,
.nxp-integration-mapping__row textarea {
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

/* compact action button: fixed small width and centered content */
.nxp-integration-mapping__row button {
    white-space: nowrap;
    width: 44px;
    min-width: 44px;
    padding: 6px 8px;
    text-align: center;
    background: none!important;
    border: none!important;
}

/* Responsive fallbacks: keep button in a compact column, let fields wrap neatly */
@media (max-width: 880px) {
    .nxp-integration-mapping__row {
        grid-template-columns: minmax(200px, 1fr) minmax(200px, 1fr) 44px;
    }
}

@media (max-width: 640px) {
    .nxp-integration-mapping__row {
        grid-template-columns: 1fr 44px;
    }
}
.nxp-integration-card textarea { width: 100%; min-height: 80px; }
</style>
