import { defineStore } from 'pinia';
import { FIELD_LIBRARY, findFieldByType } from '@/admin/constants/fields';
import { apiFetch } from '@/admin/utils/http';
import { __ } from '@/utils/translate';

const settings = window.nxpEasyForms?.builder || {};
const defaults = settings.defaults || { fields: [], options: {} };
const initialData = settings.initialData || {};
const translations = settings.lang || {};

const isObject = (value) =>
    value !== null && typeof value === 'object' && !Array.isArray(value);

const createRowId = () => Math.random().toString(36).slice(2, 10);

function baseIntegrations() {
    return {
        zapier: {
            enabled: false,
            webhook_url: '',
        },
        make: {
            enabled: false,
            webhook_url: '',
        },
        slack: {
            enabled: false,
            webhook_url: '',
            message_template: '',
        },
        teams: {
            enabled: false,
            webhook_url: '',
            card_title: '',
            message_template: '',
        },
        joomla_article: {
            enabled: false,
            category_id: 0,
            status: 'unpublished',
            author_mode: 'none',
            fixed_author_id: 0,
            language: '*',
            access: 1,
            map: {
                title: '',
                introtext: '',
                fulltext: '',
                tags: '',
                alias: '',
            },
        },
        mailchimp: {
            enabled: false,
            api_key: '',
            api_key_set: false,
            list_id: '',
            double_opt_in: false,
            email_field: '',
            first_name_field: '',
            last_name_field: '',
            tags: [],
        },
        salesforce: {
            enabled: false,
            org_id: '',
            lead_source: '',
            assignment_rule_id: '',
            debug_email: '',
            mappings: [],
        },
        hubspot: {
            enabled: false,
            access_token: '',
            access_token_set: false,
            portal_id: '',
            form_guid: '',
            email_field: '',
            field_mappings: [],
            legal_consent: false,
            consent_text: '',
        },
    };
}

function mergeIntegrations(integrations = {}) {
    const base = baseIntegrations();
    const merged = {};

    Object.keys(base).forEach((key) => {
        const baseConfig = base[key];
        const incoming = isObject(integrations[key]) ? integrations[key] : {};
        merged[key] = {
            ...baseConfig,
            ...incoming,
        };

        if (Array.isArray(baseConfig.tags)) {
            merged[key].tags = Array.isArray(incoming.tags)
                ? [...incoming.tags]
                : [];
        }

        if (Array.isArray(baseConfig.mappings)) {
            merged[key].mappings = Array.isArray(incoming.mappings)
                ? incoming.mappings.map((item) =>
                      isObject(item)
                          ? {
                                id:
                                    typeof item.id === 'string' &&
                                    item.id !== ''
                                        ? item.id
                                        : createRowId(),
                                salesforce_field: item.salesforce_field || '',
                                form_field: item.form_field || '',
                            }
                          : { salesforce_field: '', form_field: '' }
                  )
                : [];
        }

        if (Array.isArray(baseConfig.field_mappings)) {
            merged[key].field_mappings = Array.isArray(incoming.field_mappings)
                ? incoming.field_mappings.map((item) =>
                      isObject(item)
                          ? {
                                id:
                                    typeof item.id === 'string' &&
                                    item.id !== ''
                                        ? item.id
                                        : createRowId(),
                                hubspot_field: item.hubspot_field || '',
                                form_field: item.form_field || '',
                            }
                          : { hubspot_field: '', form_field: '' }
                  )
                : [];
        }

        if (Array.isArray(baseConfig.meta)) {
            merged[key].meta = Array.isArray(incoming.meta)
                ? incoming.meta.filter(isObject).map((item) => ({
                      id:
                          typeof item.id === 'string' && item.id !== ''
                              ? item.id
                              : createRowId(),
                      key: item.key || '',
                      field: item.field || '',
                  }))
                : [];
        }

        if (Array.isArray(baseConfig.static_products)) {
            merged[key].static_products = Array.isArray(
                incoming.static_products
            )
                ? incoming.static_products.filter(isObject).map((item) => ({
                      id:
                          typeof item.id === 'string' && item.id !== ''
                              ? item.id
                              : createRowId(),
                      product_id: Number(item.product_id) || 0,
                      variation_id: Number(item.variation_id) || 0,
                      quantity: Number(item.quantity) || 1,
                  }))
                : [];
        }

        if (Array.isArray(baseConfig.metadata)) {
            merged[key].metadata = Array.isArray(incoming.metadata)
                ? incoming.metadata.filter(isObject).map((item) => ({
                      id:
                          typeof item.id === 'string' && item.id !== ''
                              ? item.id
                              : createRowId(),
                      key: item.key || '',
                      field: item.field || '',
                  }))
                : [];
        }

        if (isObject(baseConfig.map)) {
            merged[key].map = {
                ...baseConfig.map,
                ...(isObject(incoming.map) ? incoming.map : {}),
            };
        }

        if (isObject(baseConfig.customer)) {
            const baseCustomer = baseConfig.customer;
            const incomingCustomer = isObject(incoming.customer)
                ? incoming.customer
                : {};
            const incomingBilling = isObject(incomingCustomer.billing)
                ? incomingCustomer.billing
                : {};
            const incomingShipping = isObject(incomingCustomer.shipping)
                ? incomingCustomer.shipping
                : {};

            merged[key].customer = {
                ...baseCustomer,
                ...incomingCustomer,
                billing: {
                    ...baseCustomer.billing,
                    ...incomingBilling,
                },
                shipping: {
                    ...baseCustomer.shipping,
                    ...incomingShipping,
                    use_billing:
                        incomingShipping.use_billing === false ? false : true,
                },
            };
        }
    });

    return merged;
}

function baseOptions() {
    return {
        store_submissions: true,
        send_email: true,
        email_recipient: '',
        email_subject: '',
        email_from_name: '',
        email_from_address: '',
        honeypot: true,
        ip_storage: 'anonymous',
        throttle: {
            max_requests: 3,
            per_seconds: 10,
        },
        success_message: __(
            'Thanks! Your message has been sent.',
            'nxp-easy-forms'
        ),
        error_message: __(
            'Something went wrong. Please fix the errors and try again.',
            'nxp-easy-forms'
        ),
        captcha: {
            provider: 'none',
            recaptcha_v3: {
                site_key: '',
                secret_key: '',
            },
            turnstile: {
                site_key: '',
                secret_key: '',
            },
            friendlycaptcha: {
                site_key: '',
                secret_key: '',
            },
        },
        email_delivery: {
            provider: 'joomla',
            sendgrid: {
                api_key: '',
            },
            mailgun: {
                api_key: '',
                domain: '',
                region: 'us',
            },
            postmark: {
                api_token: '',
            },
            brevo: {
                api_key: '',
            },
            amazon_ses: {
                access_key: '',
                secret_key: '',
                region: 'us-east-1',
            },
            mailpit: {
                host: '127.0.0.1',
                port: 1025,
            },
            smtp2go: {
                api_key: '',
            },
            smtp: {
                host: '',
                port: 587,
                encryption: 'tls',
                username: '',
                password: '',
                password_set: false,
            },
        },
        custom_css: '',
        webhooks: {
            enabled: false,
            endpoint: '',
            secret: '',
        },
        integrations: baseIntegrations(),
    };
}

function mergeOptions(options = {}) {
    const base = baseOptions();
    const incoming = options || {};

    return {
        ...base,
        ...incoming,
        throttle: {
            ...base.throttle,
            ...(incoming.throttle || {}),
        },
        captcha: (() => {
            const incomingCaptcha = incoming.captcha || {};
            const provider = incomingCaptcha.provider || base.captcha.provider;

            // Migrate legacy flat structure to provider-specific nested structure
            const legacySiteKey = incomingCaptcha.site_key;
            const legacySecretKey = incomingCaptcha.secret_key;
            const hasLegacyKeys = legacySiteKey !== undefined || legacySecretKey !== undefined;

            // If we have legacy keys and a valid provider, migrate them
            const migratedProvider = {};
            if (hasLegacyKeys && provider !== 'none' && (provider === 'recaptcha_v3' || provider === 'turnstile' || provider === 'friendlycaptcha')) {
                migratedProvider[provider] = {
                    site_key: legacySiteKey || '',
                    secret_key: legacySecretKey || '',
                };
            }

            const result = {
                ...base.captcha,
                ...incomingCaptcha,
                recaptcha_v3: {
                    ...base.captcha.recaptcha_v3,
                    ...(migratedProvider.recaptcha_v3 || incomingCaptcha.recaptcha_v3 || {}),
                },
                turnstile: {
                    ...base.captcha.turnstile,
                    ...(migratedProvider.turnstile || incomingCaptcha.turnstile || {}),
                },
                friendlycaptcha: {
                    ...base.captcha.friendlycaptcha,
                    ...(migratedProvider.friendlycaptcha || incomingCaptcha.friendlycaptcha || {}),
                },
            };

            // Remove legacy keys from the final object
            delete result.site_key;
            delete result.secret_key;

            return result;
        })(),
        email_delivery: {
            ...base.email_delivery,
            ...(incoming.email_delivery || {}),
            sendgrid: {
                ...base.email_delivery.sendgrid,
                ...(incoming.email_delivery?.sendgrid || {}),
            },
            mailgun: {
                ...base.email_delivery.mailgun,
                ...(incoming.email_delivery?.mailgun || {}),
            },
            postmark: {
                ...base.email_delivery.postmark,
                ...(incoming.email_delivery?.postmark || {}),
            },
            brevo: {
                ...base.email_delivery.brevo,
                ...(incoming.email_delivery?.brevo || {}),
            },
            amazon_ses: {
                ...base.email_delivery.amazon_ses,
                ...(incoming.email_delivery?.amazon_ses || {}),
            },
            mailpit: {
                ...base.email_delivery.mailpit,
                ...(incoming.email_delivery?.mailpit || {}),
            },
            smtp2go: {
                ...base.email_delivery.smtp2go,
                ...(incoming.email_delivery?.smtp2go || {}),
            },
            smtp: {
                ...base.email_delivery.smtp,
                ...(incoming.email_delivery?.smtp || {}),
            },
        },
        webhooks: {
            ...base.webhooks,
            ...(incoming.webhooks || {}),
        },
        integrations: mergeIntegrations(incoming.integrations || {}),
    };
}

function makeOptions() {
    return mergeOptions(defaults.options || {});
}

function generateFieldId(type) {
    return `${type}-${Math.random().toString(36).slice(2, 8)}`;
}

function generateFieldName(type) {
    return `${type}_${Math.random().toString(36).slice(2, 8)}`;
}

function createField(type) {
    const template = findFieldByType(type);

    if (!template) {
        return null;
    }

    const field = {
        id: generateFieldId(type),
        type,
        name: generateFieldName(type),
        label: template.label,
        placeholder: template.default?.placeholder || '',
        required: template.default?.required ?? false,
        options: template.default?.options ? [...template.default.options] : [],
        multiple: template.default?.multiple ?? false,
        content: template.default?.content || '',
        accept: template.default?.accept || '',
        value: template.default?.value ?? '',
    };

    if (type === 'file' && template.default?.maxFileSize) {
        field.maxFileSize = template.default.maxFileSize;
    }

    if (type === 'state') {
        field.country_field = template.default?.country_field || '';
        field.allow_text_input = template.default?.allow_text_input !== false;
    }

    return field;
}

export const useFormStore = defineStore('form', {
    state: () => ({
        formId: settings.formId || 0,
        title:
            initialData.title ||
            translations.defaultTitle ||
            __('Untitled form', 'nxp-easy-forms'),
        alias: initialData.alias || '',
        fields: Array.isArray(initialData.fields) ? initialData.fields.map((field) => ({
            ...field,
            multiple: field.multiple ?? false,
            country_field:
                field.type === 'state'
                    ? field.country_field || ''
                    : field.country_field,
            allow_text_input:
                field.type === 'state'
                    ? field.allow_text_input !== false
                    : field.allow_text_input,
        })) : [],
        options: initialData.settings ? mergeOptions(initialData.settings) : makeOptions(),
        loading: false,
        saving: false,
        error: null,
        notice: null,
        hasUnsavedChanges: false,
    }),
    getters: {
        isDirty: (state) => state.hasUnsavedChanges,
    },
    actions: {
        async bootstrap() {
            // If we have initial data from server, we don't need to fetch via AJAX
            if (initialData.title || (Array.isArray(initialData.fields) && initialData.fields.length > 0)) {
                // Data already loaded in state initialization
                return;
            }

            if (!this.formId) {
                this.options = makeOptions();
                return;
            }

            this.loading = true;

            try {
                const response = await apiFetch('forms/get', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: this.formId }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        data?.message ||
                            __('Unable to load form.', 'nxp-easy-forms')
                    );
                }
                this.title = data.title;
                this.alias = data.alias || '';
                this.fields = Array.isArray(data.config?.fields)
                    ? data.config.fields.map((field) => ({
                          ...field,
                          // Preserve type-specific props and ensure booleans are sane
                          multiple: field.multiple ?? false,
                          country_field:
                              field.type === 'state'
                                  ? field.country_field || ''
                                  : field.country_field,
                          allow_text_input:
                              field.type === 'state'
                                  ? field.allow_text_input !== false
                                  : field.allow_text_input,
                      }))
                    : [];
                this.options = mergeOptions(data.config?.options || {});
                this.hasUnsavedChanges = false;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        setTitle(value) {
            this.title = value;
            this.hasUnsavedChanges = true;
        },
        addField(type, index = null) {
            const field = createField(type);

            if (!field) {
                return;
            }

            if (index === null || index >= this.fields.length) {
                this.fields.push(field);
            } else {
                this.fields.splice(index, 0, field);
            }
            this.hasUnsavedChanges = true;
        },
        removeField(id) {
            const idx = this.fields.findIndex((field) => field.id === id);
            if (idx >= 0) {
                this.fields.splice(idx, 1);
                this.hasUnsavedChanges = true;
            }
        },
        duplicateField(id) {
            const idx = this.fields.findIndex((field) => field.id === id);
            if (idx === -1) return;
            const source = this.fields[idx];
            const copy = {
                ...source,
                id: generateFieldId(source.type),
                name: generateFieldName(source.type),
                options: Array.isArray(source.options)
                    ? [...source.options]
                    : [],
                multiple: source.multiple ?? false,
            };
            this.fields.splice(idx + 1, 0, copy);
            this.hasUnsavedChanges = true;
        },
        updateField(id, payload) {
            const field = this.fields.find((item) => item.id === id);
            if (!field) return;
            Object.assign(field, payload);
            this.hasUnsavedChanges = true;
        },
        updateFieldOrder(oldIndex, newIndex) {
            if (oldIndex === newIndex) return;
            const item = this.fields.splice(oldIndex, 1)[0];
            this.fields.splice(newIndex, 0, item);
            this.hasUnsavedChanges = true;
        },
        updateOptions(patch) {
            this.options = {
                ...this.options,
                ...patch,
            };
            this.hasUnsavedChanges = true;
        },
        updateThrottle(patch) {
            this.options.throttle = {
                ...this.options.throttle,
                ...patch,
            };
            this.hasUnsavedChanges = true;
        },
        applyTemplate(template) {
            this.fields = template.fields.map((field) => {
                const baseField = {
                    id: generateFieldId(field.type),
                    type: field.type,
                    name: field.name || generateFieldName(field.type),
                    label: field.label || '',
                    placeholder: field.placeholder || '',
                    required: field.required ?? false,
                    options: Array.isArray(field.options)
                        ? [...field.options]
                        : [],
                    multiple:
                        field.type === 'select'
                            ? Boolean(field.multiple)
                            : false,
                    content: field.content || '',
                    accept: field.accept || '',
                };

                if (field.type === 'file' && field.maxFileSize) {
                    baseField.maxFileSize = field.maxFileSize;
                }

                if (field.type === 'state') {
                    baseField.country_field = field.country_field || '';
                    baseField.allow_text_input =
                        field.allow_text_input !== false;
                }

                return baseField;
            });

            if (template.options) {
                this.options = mergeOptions(template.options || {});
            } else {
                this.options = mergeOptions({});
            }
            this.hasUnsavedChanges = true;
        },
        clearNotice() {
            this.notice = null;
        },
        clearError() {
            this.error = null;
        },
        async saveForm() {
            if (this.saving) {
                return;
            }

            this.saving = true;
            this.error = null;
            this.notice = null;

            const payload = {
                title:
                    this.title ||
                    translations.defaultTitle ||
                    __('Untitled form', 'nxp-easy-forms'),
                alias: this.alias || '',
                config: {
                    fields: this.fields.map((field) => {
                        const mapped = {
                            id: field.id,
                            type: field.type,
                            name: field.name,
                            label: field.label,
                            placeholder: field.placeholder,
                            required: field.required,
                            options: field.options || [],
                            multiple: field.multiple ?? false,
                            content: field.content || '',
                            accept: field.accept || '',
                        };

                        if (field.type === 'file' && field.maxFileSize) {
                            mapped.maxFileSize = field.maxFileSize;
                        }

                        if (field.type === 'state') {
                            mapped.country_field = field.country_field || '';
                            mapped.allow_text_input =
                                field.allow_text_input !== false;
                        }

                        return mapped;
                    }),
                    options: this.options,
                },
            };

            const wasNewForm = !this.formId;

            try {
                const response = await apiFetch('forms/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: Number(this.formId) || 0,
                        ...payload,
                    }),
                });

                const responseBody = await response.json().catch(() => ({}));
                const responseData = responseBody?.data ?? responseBody;

                if (!response.ok) {
                    throw new Error(
                        responseData?.message ||
                            responseBody?.message ||
                            __('Unable to save form.', 'nxp-easy-forms')
                    );
                }

                const resolvedId = Number(responseData?.id) || 0;

                if (resolvedId > 0) {
                    this.formId = resolvedId;
                }

                if (typeof responseData?.alias === 'string') {
                    this.alias = responseData.alias;
                }

                if (wasNewForm && this.formId && settings.builderUrl) {
                    const target = `${settings.builderUrl}&form_id=${this.formId}`;
                    this.hasUnsavedChanges = false;
                    if (
                        typeof window !== 'undefined' &&
                        window.history?.replaceState
                    ) {
                        window.history.replaceState({}, '', target);
                    }
                    settings.formId = this.formId;
                }

                const savedMessage =
                    translations.formSaved ||
                    __('Form saved.', 'nxp-easy-forms');
                const createdMessage =
                    translations.formCreated ||
                    __('Form created.', 'nxp-easy-forms');

                this.notice = wasNewForm ? createdMessage : savedMessage;
                this.hasUnsavedChanges = false;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
        async sendTestEmail({ recipient, options, formId }) {
            const response = await apiFetch('emails/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ recipient, options, formId }),
            });

            const result = await response.json().catch(() => ({}));
            const payload = result?.data ?? result;

            if (!response.ok || !payload?.sent) {
                throw new Error(
                    payload?.message ||
                        result?.message ||
                        __('Test email failed.', 'nxp-easy-forms')
                );
            }

            return payload;
        },
    },
});

// Intentionally do not re-export FIELD_LIBRARY to avoid tight coupling.
