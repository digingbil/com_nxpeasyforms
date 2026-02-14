import { __ } from '@/utils/translate';

/**
 * Default form options structure
 */
export const formDefaults = {
    store_submissions: true,
    send_email: true,
    use_global_email_delivery: true,
    use_global_recipient: true,
    use_global_from_name: true,
    use_global_from_email: true,
    email_recipient: '',
    email_subject: '',
    email_from_name: '',
    email_from_address: '',
    honeypot: true,
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
    ip_storage: 'anonymous',
    delete_submissions_after_days_enabled: false,
    delete_submissions_after_days: 90,
    captcha: {
        provider: 'none',
        recaptcha_v3: {
            site_key: '',
            secret_key: '',
            secret_key_set: false,
            remove_secret: false,
        },
        turnstile: {
            site_key: '',
            secret_key: '',
            secret_key_set: false,
            remove_secret: false,
        },
        friendlycaptcha: {
            site_key: '',
            secret_key: '',
            secret_key_set: false,
            remove_secret: false,
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
        secret_set: false,
        remove_secret: false,
    },
    integrations: {
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
                featured_image: '',
                featured_image_alt: '',
                featured_image_caption: '',
            },
        },
        user_registration: {
            enabled: false,
            user_group: 2,
            require_activation: true,
            send_activation_email: true,
            auto_login: false,
            password_mode: 'auto', // 'auto' | 'mapped'
            field_mapping: {
                username: 'username',
                email: 'email',
                password: 'password',
                name: '',
            },
        },
        user_login: {
            enabled: false,
            identity_mode: 'auto', // 'auto' | 'username' | 'email'
            remember_me: true,
            redirect_url: '',
            field_mapping: {
                identity: 'username', // or 'email'
                password: 'password',
                twofactor: '',
            },
        },
        mailchimp: {
            enabled: false,
            api_key: '',
            api_key_set: false,
            remove_api_key: false,
            list_id: '',
            double_opt_in: false,
            email_field: '',
            first_name_field: '',
            last_name_field: '',
            tags_input: '',
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
            remove_access_token: false,
            portal_id: '',
            form_guid: '',
            email_field: '',
            field_mappings: [],
            legal_consent: false,
            consent_text: '',
        },
    },
};

/**
 * Deep merge utility that merges source into target
 * Arrays are replaced, not merged
 * Objects are recursively merged
 * @param {Object} target - Target object
 * @param {Object} source - Source object
 * @returns {Object} Merged object
 */
export function deepMerge(target, source) {
    const output = { ...target };

    if (!isObject(target) || !isObject(source)) {
        return output;
    }

    Object.keys(source).forEach((key) => {
        const sourceValue = source[key];
        const targetValue = target[key];

        if (Array.isArray(sourceValue)) {
            // Arrays are replaced, not merged
            output[key] = sourceValue;
        } else if (isObject(sourceValue)) {
            // Recursively merge objects
            output[key] = deepMerge(targetValue || {}, sourceValue);
        } else {
            // Primitive values are replaced
            output[key] = sourceValue;
        }
    });

    return output;
}

/**
 * Check if value is a plain object
 * @param {*} value
 * @returns {boolean}
 */
function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Create a fresh copy of form defaults
 * @returns {Object}
 */
export function createFormDefaults() {
    return JSON.parse(JSON.stringify(formDefaults));
}
