import { __ } from '@/utils/i18n';

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
        site_key: '',
        secret_key: '',
    },
    email_delivery: {
        provider: 'wordpress',
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
        teams: {
            enabled: false,
            webhook_url: '',
            card_title: '',
            message_template: '',
        },
        wordpress_post: {
            enabled: false,
            post_type: 'post',
            post_status: 'pending',
            author_mode: 'current_user',
            fixed_author_id: 0,
            map: {
                title: '',
                content: '',
                excerpt: '',
                featured_image: '',
            },
            taxonomies: [],
            meta: [],
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
        woocommerce: {
            enabled: false,
            product_mode: 'static',
            static_products: [],
            product_field: '',
            quantity_field: '',
            variation_field: '',
            price_field: '',
            order_status: 'wc-pending',
            create_customer: true,
            customer: {
                email_field: '',
                first_name_field: '',
                last_name_field: '',
                phone_field: '',
                company_field: '',
                billing: {
                    line1_field: '',
                    line2_field: '',
                    city_field: '',
                    state_field: '',
                    postcode_field: '',
                    country_field: '',
                },
                shipping: {
                    use_billing: true,
                    line1_field: '',
                    line2_field: '',
                    city_field: '',
                    state_field: '',
                    postcode_field: '',
                    country_field: '',
                },
            },
            metadata: [],
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
