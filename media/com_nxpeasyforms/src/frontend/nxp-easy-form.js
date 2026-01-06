/**
 * Form handler class
 */
export class NxpEasyForm {
    constructor(wrapper, config) {
        this.wrapper = wrapper;
        this.formElement = wrapper.querySelector('.nxp-easy-form__form');
        this.formId = Number(wrapper.dataset.formId || 0);
        this.messageEl = this.formElement.querySelector(
            '.nxp-easy-form__messages'
        );
        this.submitButton = this.formElement.querySelector(
            '.nxp-easy-form__submit'
        );

        this.config = config;
        this.restUrl = config.restUrl;
        this.successMessage =
            this.formElement.dataset.successMessage ||
            config.successMessage ||
            'Thanks!';
        this.errorMessage =
            this.formElement.dataset.errorMessage ||
            config.errorMessage ||
            'Submission failed. Please try again.';

        this.captchaConfig = {
            provider: wrapper.dataset.captchaProvider || 'none',
            siteKey: wrapper.dataset.captchaSiteKey || '',
        };

        this.scriptLoader = config.scriptLoader;
        this.isSubmitting = false;

        // Detect if this is a login form (has user_login integration enabled)
        this.isLoginForm = wrapper.dataset.isLoginForm === '1';

        this.init();
    }

    init() {
        // Preload captcha script if needed
        if (this.captchaConfig.provider !== 'none') {
            this.scriptLoader
                .loadCaptchaScript(
                    this.captchaConfig.provider,
                    this.captchaConfig.siteKey
                )
                .catch(() => {
                    /* noop - handled during submission */
                });
        }

        // Bind submit handler
        this.formElement.addEventListener('submit', (e) =>
            this.handleSubmit(e)
        );
    }

    async handleSubmit(event) {
        event.preventDefault();

        this.clearMessages();
        this.clearErrors();
        this.setSubmitting(true);

        try {
            const { data: payload, files } = this.buildPayload();
            const captchaStatus = await this.attachCaptchaToken(payload);

            if (captchaStatus !== true) {
                const message =
                    typeof captchaStatus === 'string'
                        ? captchaStatus
                        : this.config.captchaFailedMessage;
                this.showMessage('error', message);
                requestAnimationFrame(() => this.focusFirstInvalid());
                return;
            }

            // Add formId to payload
            payload.formId = this.formId;

            const requestOptions = this.buildRequestOptions(payload, files);

            // Use Site controller for login forms to establish a real session
            const endpoint = this.isLoginForm
                ? `${window.location.origin}/index.php?option=com_nxpeasyforms&task=login.submit&format=json`
                : `${this.restUrl}/submission`;

            const response = await fetch(endpoint, requestOptions);

            const result = await response.json().catch(() => ({}));

            // Handle nested JsonResponse wrapper (data.errors vs errors)
            const actualData = result.data || result;
            const isSuccess =
                response.ok && (result.success || actualData.success);

            if (!isSuccess) {
                const errorFields =
                    actualData?.errors?.fields || result?.errors?.fields;
                if (errorFields) {
                    this.applyErrors(errorFields);
                }
                const message =
                    actualData.message || result.message || this.errorMessage;
                this.showMessage('error', message);
                requestAnimationFrame(() => this.focusFirstInvalid());
                return;
            }

            this.showMessage(
                'success',
                actualData.message || result.message || this.successMessage
            );
            this.formElement.reset();
            this.resetCaptcha();

            // Handle redirect for login forms
            if (this.isLoginForm && (actualData.redirect || result.redirect)) {
                const redirectUrl = actualData.redirect || result.redirect;
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1000);
            }
        } catch (error) {
            this.showMessage('error', this.errorMessage);
        } finally {
            this.setSubmitting(false);
        }
    }

    buildPayload() {
        const formData = new FormData(this.formElement);
        const payload = {};
        const files = {};
        const checkboxState = {};

        formData.forEach((value, key) => {
            if (value instanceof File) {
                if (value && value.name) {
                    files[key] = value;
                }
            } else {
                payload[key] = value;
            }
        });

        this.formElement
            .querySelectorAll('input[type="checkbox"][name]')
            .forEach((checkbox) => {
                const name = checkbox.name;
                if (!checkboxState[name]) {
                    checkboxState[name] = { total: 0, values: [] };
                }
                checkboxState[name].total += 1;
                if (checkbox.checked) {
                    const value = checkbox.value || 'on';
                    checkboxState[name].values.push(value);
                }
                if (
                    !checkbox.checked &&
                    checkboxState[name].total === 1 &&
                    !(name in payload)
                ) {
                    payload[name] = false;
                }
            });

        Object.entries(checkboxState).forEach(([name, state]) => {
            if (state.total > 1) {
                payload[name] = state.values;
            } else {
                payload[name] = state.values.length > 0;
            }
        });

        this.formElement
            .querySelectorAll('select[multiple][name]')
            .forEach((select) => {
                const values = Array.from(select.selectedOptions).map(
                    (option) => option.value
                );
                payload[select.name] = values;
            });

        Object.keys(files).forEach((key) => {
            delete payload[key];
        });

        return { data: payload, files };
    }

    buildRequestOptions(payload, files) {
        const hasFiles = Object.keys(files).length > 0;

        if (!hasFiles) {
            return {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            };
        }

        const formData = new FormData();
        formData.append('payload', JSON.stringify(payload));

        Object.entries(files).forEach(([name, file]) => {
            formData.append(`files[${name}]`, file);
        });

        return {
            method: 'POST',
            body: formData,
        };
    }

    async attachCaptchaToken(payload) {
        if (!this.captchaConfig || this.captchaConfig.provider === 'none') {
            delete payload._nxp_captcha_token;
            delete payload._nxp_captcha_provider;
            return true;
        }

        payload._nxp_captcha_provider = this.captchaConfig.provider;

        if (this.captchaConfig.provider === 'recaptcha_v3') {
            return await this.handleRecaptchaV3(payload);
        }

        if (this.captchaConfig.provider === 'turnstile') {
            return this.handleTurnstile(payload);
        }

        if (this.captchaConfig.provider === 'friendlycaptcha') {
            return this.handleFriendlyCaptcha(payload);
        }

        return true;
    }

    async handleRecaptchaV3(payload) {
        if (!this.captchaConfig.siteKey) {
            return this.config.captchaFailedMessage;
        }

        try {
            await this.scriptLoader.loadCaptchaScript(
                'recaptcha_v3',
                this.captchaConfig.siteKey
            );

            if (!window.grecaptcha || !window.grecaptcha.execute) {
                return this.config.captchaFailedMessage;
            }

            const token = await new Promise((resolve, reject) => {
                window.grecaptcha.ready(() => {
                    window.grecaptcha
                        .execute(this.captchaConfig.siteKey, {
                            action: 'nxp_easy_forms_submit',
                        })
                        .then(resolve)
                        .catch(reject);
                });
            });

            if (!token) {
                return this.config.captchaFailedMessage;
            }

            payload._nxp_captcha_token = token;
            const hidden = this.formElement.querySelector(
                'input[name="_nxp_captcha_token"]'
            );
            if (hidden) {
                hidden.value = token;
            }

            return true;
        } catch (error) {
            return this.config.captchaFailedMessage;
        }
    }

    async handleTurnstile(payload) {
        await this.scriptLoader.loadCaptchaScript('turnstile');
        const input = this.formElement.querySelector(
            'input[name="cf-turnstile-response"]'
        );
        const token = (
            payload['cf-turnstile-response'] ||
            input?.value ||
            ''
        ).trim();

        if (!token) {
            return this.config.captchaIncompleteMessage;
        }

        payload._nxp_captcha_token = token;
        delete payload['cf-turnstile-response'];
        const hidden = this.formElement.querySelector(
            'input[name="_nxp_captcha_token"]'
        );
        if (hidden) {
            hidden.value = token;
        }

        return true;
    }

    async handleFriendlyCaptcha(payload) {
        await this.scriptLoader.loadCaptchaScript('friendlycaptcha');
        const token = (
            payload['frc-captcha-response'] ||
            payload['frc-captcha-solution'] ||
            this.formElement.querySelector('input[name="frc-captcha-response"]')
                ?.value ||
            this.formElement.querySelector('input[name="frc-captcha-solution"]')
                ?.value ||
            ''
        ).trim();

        if (!token) {
            return this.config.captchaIncompleteMessage;
        }

        payload._nxp_captcha_token = token;
        delete payload['frc-captcha-response'];
        delete payload['frc-captcha-solution'];
        const hidden = this.formElement.querySelector(
            'input[name="_nxp_captcha_token"]'
        );
        if (hidden) {
            hidden.value = token;
        }

        return true;
    }

    resetCaptcha() {
        if (!this.captchaConfig || this.captchaConfig.provider === 'none') {
            return;
        }

        if (
            this.captchaConfig.provider === 'turnstile' &&
            window.turnstile?.reset
        ) {
            window.turnstile.reset();
        }

        if (
            this.captchaConfig.provider === 'friendlycaptcha' &&
            window.friendlyChallenge?.widgets?.reset
        ) {
            try {
                window.friendlyChallenge.widgets.reset();
            } catch (error) {
                // Ignore reset issues.
            }
        }

        const hidden = this.formElement.querySelector(
            'input[name="_nxp_captcha_token"]'
        );
        if (hidden) {
            hidden.value = '';
        }
    }

    showMessage(type, text) {
        if (!this.messageEl) return;
        this.messageEl.innerHTML = '';
        const notice = document.createElement('div');
        notice.className = `nxp-easy-form__notice nxp-easy-form__notice--${type}`;
        notice.id = `nxp-ef-message-${this.formId}`;
        notice.setAttribute('role', 'status');
        notice.setAttribute('tabindex', '-1');
        notice.textContent = text;
        this.messageEl.appendChild(notice);

        requestAnimationFrame(() => {
            notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => {
                notice.focus({ preventScroll: true });
            }, 300);
        });
    }

    clearMessages() {
        if (this.messageEl) {
            this.messageEl.innerHTML = '';
        }
    }

    setSubmitting(isSubmitting) {
        this.isSubmitting = isSubmitting;
        if (this.submitButton) {
            this.submitButton.disabled = isSubmitting;
        }
        this.wrapper.classList.toggle(
            'nxp-easy-form--submitting',
            isSubmitting
        );
    }

    applyErrors(errors) {
        let hasErrors = false;

        Object.entries(errors).forEach(([name, message]) => {
            const errorHolder =
                this.formElement.querySelector(
                    `[data-error-for="${this.escapeSelector(name)}"]`
                ) || this.ensureErrorHolder(name);

            if (!errorHolder) {
                return;
            }

            hasErrors = true;

            const control = this.formElement.querySelector(
                this.getFieldSelector(name)
            );

            if (control) {
                control.setAttribute('aria-invalid', 'true');
                const errorId = this.ensureErrorId(errorHolder, control);
                control.setAttribute('aria-describedby', errorId);
            }

            errorHolder.textContent = message;
            errorHolder
                .closest('.nxp-easy-form__group')
                ?.classList.add('nxp-easy-form__group--error');
        });

        if (hasErrors) {
            requestAnimationFrame(() => this.focusFirstInvalid());
        }
    }

    clearErrors() {
        this.formElement
            .querySelectorAll('.nxp-easy-form__group--error')
            .forEach((group) => {
                group.classList.remove('nxp-easy-form__group--error');
            });

        this.formElement
            .querySelectorAll('.nxp-easy-form__error')
            .forEach((error) => {
                const control = this.formElement.querySelector(
                    this.getFieldSelector(error.dataset.errorFor)
                );
                if (control) {
                    control.removeAttribute('aria-invalid');
                    if (control.getAttribute('aria-describedby') === error.id) {
                        control.removeAttribute('aria-describedby');
                    }
                }
                error.textContent = '';
            });
    }

    ensureErrorHolder(fieldName) {
        const field = this.formElement.querySelector(
            this.getFieldSelector(fieldName)
        );
        const group = field?.closest('.nxp-easy-form__group');

        if (!group) {
            return null;
        }

        const errorHolder = document.createElement('p');
        errorHolder.className = 'nxp-easy-form__error';
        errorHolder.dataset.errorFor = fieldName;
        errorHolder.setAttribute('role', 'alert');
        group.appendChild(errorHolder);

        return errorHolder;
    }

    ensureErrorId(errorHolder, control) {
        if (errorHolder.id) {
            return errorHolder.id;
        }
        const baseId = control.id || control.name || 'nxp-ef-field';
        const sanitisedId = baseId.replace(/[^a-z0-9_-]/gi, '');
        const errorId = `${sanitisedId || 'nxp-ef-field'}-error`;
        errorHolder.id = errorId;
        return errorId;
    }

    focusFirstInvalid() {
        const invalid = this.formElement.querySelector('[aria-invalid="true"]');
        if (invalid) {
            invalid.focus({ preventScroll: false });
        }
    }

    getFieldSelector(name) {
        const escaped = this.escapeSelector(name);
        return `[name="${escaped}"], [id="${escaped}"]`;
    }

    escapeSelector(value) {
        if (window.CSS?.escape) {
            return window.CSS.escape(String(value));
        }

        return String(value).replace(
            /([ #;?%&,.+*~':"!^$\[\]\\(){}|<>@])/g,
            '\\$1'
        );
    }
}
