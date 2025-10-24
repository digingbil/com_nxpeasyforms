import { ScriptLoader } from "./script-loader.js";
import { NxpEasyForm } from "./nxp-easy-form.js";
import { CountryStateHandler } from "./country-state-handler.js";

/**
 * Initialize all forms on the page
 */
(() => {
    const wrappers = document.querySelectorAll(".nxp-easy-form");
    if (!wrappers.length) {
        return;
    }

    const settings = window.nxpEasyFormsFrontend || {};
    const restApiRoot =
        window.wpApiSettings?.root || `${window.location.origin}/wp-json/`;
    const restUrl =
        settings.restUrl ||
        `${restApiRoot.replace(/\/$/, "")}/nxp-easy-forms/v1`;

    const config = {
        restUrl,
        successMessage: settings.successMessage || "Thanks!",
        errorMessage:
            settings.errorMessage || "Submission failed. Please try again.",
        captchaFailedMessage:
            settings.captchaFailedMessage ||
            "Security verification failed. Please try again.",
        captchaIncompleteMessage:
            settings.captchaIncompleteMessage ||
            "Please complete the verification.",
        scriptLoader: new ScriptLoader(),
    };

    // Initialize country/state handler once for all forms
    new CountryStateHandler(restUrl);

    wrappers.forEach((wrapper) => {
        if (wrapper.dataset.nxpEfBooted === "1") {
            return;
        }

        const formElement = wrapper.querySelector(".nxp-easy-form__form");
        const formId = Number(wrapper.dataset.formId || 0);

        if (!formElement || !restUrl || !formId) {
            return;
        }

        wrapper.dataset.nxpEfBooted = "1";

        new NxpEasyForm(wrapper, config);
    });
})();
