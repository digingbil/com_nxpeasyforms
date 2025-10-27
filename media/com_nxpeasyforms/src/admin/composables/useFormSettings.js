import { ref } from "vue";
import { safeTrim } from "@/admin/utils/strings";
import { apiFetch } from "@/admin/utils/http";
import { __ } from "@/utils/translate";

/**
 * Composable for form settings operations
 */
export function useFormSettings() {
    const mailchimpAudiences = ref([]);
    const mailchimpAudiencesLoading = ref(false);
    const mailchimpAudiencesError = ref(null);
    const mailchimpAudiencesFetched = ref(false);

    /**
     * Fetch Mailchimp audiences (lazy-loaded)
     * @param {Object} params
     * @param {string} params.apiKey - Mailchimp API key
     * @param {number} params.formId - Form ID
     * @param {string} params.restUrl - REST API URL
     * @param {string} params.nonce - CSRF token
     * @returns {Promise<void>}
     */
    const fetchMailchimpAudiences = async ({
        apiKey,
        formId,
        restUrl,
        nonce,
    }) => {
        if (!restUrl) {
            return;
        }

        // Don't refetch if already loaded
        if (mailchimpAudiencesFetched.value) {
            return;
        }

        mailchimpAudiencesError.value = null;
        mailchimpAudiencesLoading.value = true;

        try {
            const response = await apiFetch('integrations/mailchimp/lists', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    apiKey: safeTrim(apiKey) || undefined,
                    formId: formId,
                }),
            }, { nonce, base: restUrl });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data?.message ||
                        __("Unable to fetch Mailchimp audiences.", "nxp-easy-forms")
                );
            }

            mailchimpAudiences.value = Array.isArray(data?.lists) ? data.lists : [];
            mailchimpAudiencesFetched.value = true;
        } catch (error) {
            mailchimpAudiencesError.value =
                error?.message ||
                __("Unexpected Mailchimp API error.", "nxp-easy-forms");
        } finally {
            mailchimpAudiencesLoading.value = false;
        }
    };

    /**
     * Reset Mailchimp audiences state (for forcing refetch)
     */
    const resetMailchimpAudiences = () => {
        mailchimpAudiences.value = [];
        mailchimpAudiencesError.value = null;
        mailchimpAudiencesLoading.value = false;
        mailchimpAudiencesFetched.value = false;
    };

    return {
        mailchimpAudiences,
        mailchimpAudiencesLoading,
        mailchimpAudiencesError,
        mailchimpAudiencesFetched,
        fetchMailchimpAudiences,
        resetMailchimpAudiences,
    };
}

/**
 * Debounce a function call
 * @param {Function} fn - Function to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns {Function} Debounced function
 */
export function debounce(fn, delay = 300) {
    let timeoutId = null;
    return function (...args) {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            fn.apply(this, args);
        }, delay);
    };
}
