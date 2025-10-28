(() => {
    const settings = window.nxpEasyFormsFrontend || {};
    const restUrl = settings.restUrl || '';

    if (!restUrl) {
        return;
    }

    const scriptCache = new Map();

    function loadScript(url) {
        if (!scriptCache.has(url)) {
            scriptCache.set(
                url,
                new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = url;
                    script.async = true;
                    script.defer = true;
                    script.onload = () => resolve();
                    script.onerror = () =>
                        reject(new Error(`Failed to load ${url}`));
                    document.head.appendChild(script);
                })
            );
        }

        return scriptCache.get(url);
    }

    function preloadCaptcha(provider, siteKey) {
        switch (provider) {
            case 'recaptcha_v3':
                if (siteKey) {
                    return loadScript(
                        `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(
                            siteKey
                        )}`
                    ).catch(() => {});
                }
                break;
            case 'turnstile':
                return loadScript(
                    'https://challenges.cloudflare.com/turnstile/v0/api.js'
                ).catch(() => {});
            case 'friendlycaptcha':
                return loadScript(
                    'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.8/widget.min.js'
                ).catch(() => {});
            default:
        }

        return Promise.resolve();
    }

    async function attachCaptchaToken(form, formData, config) {
        if (!config || config.provider === 'none') {
            formData.delete('_nxp_captcha_token');
            formData.delete('_nxp_captcha_provider');
            return true;
        }

        formData.set('_nxp_captcha_provider', config.provider);

        if (config.provider === 'recaptcha_v3') {
            if (!config.siteKey) {
                return settings.captchaFailedMessage;
            }

            try {
                await loadScript(
                    `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(
                        config.siteKey
                    )}`
                );

                if (!window.grecaptcha || !window.grecaptcha.execute) {
                    return settings.captchaFailedMessage;
                }

                const token = await new Promise((resolve, reject) => {
                    window.grecaptcha.ready(() => {
                        window.grecaptcha
                            .execute(config.siteKey, {
                                action: 'nxp_easy_forms_submit',
                            })
                            .then(resolve)
                            .catch(reject);
                    });
                });

                if (!token) {
                    return settings.captchaFailedMessage;
                }

                formData.set('_nxp_captcha_token', token);

                const hidden = form.querySelector(
                    'input[name="_nxp_captcha_token"]'
                );
                if (hidden) {
                    hidden.value = token;
                }

                return true;
            } catch (error) {
                return settings.captchaFailedMessage;
            }
        }

        if (config.provider === 'turnstile') {
            await loadScript(
                'https://challenges.cloudflare.com/turnstile/v0/api.js'
            ).catch(() => {});

            const token =
                formData.get('cf-turnstile-response') ||
                form.querySelector('input[name="cf-turnstile-response"]')
                    ?.value ||
                '';

            if (!token) {
                return settings.captchaFailedMessage;
            }

            formData.set('_nxp_captcha_token', token);
            return true;
        }

        if (config.provider === 'friendlycaptcha') {
            await loadScript(
                'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.8/widget.min.js'
            ).catch(() => {});

            const token =
                formData.get('frc-captcha-response') ||
                formData.get('frc-captcha-solution') ||
                form.querySelector('input[name="frc-captcha-response"]')
                    ?.value ||
                form.querySelector('input[name="frc-captcha-solution"]')
                    ?.value ||
                '';

            if (!token) {
                return settings.captchaFailedMessage;
            }

            formData.set('_nxp_captcha_token', token);
            formData.delete('frc-captcha-response');
            formData.delete('frc-captcha-solution');
            return true;
        }

        return true;
    }

    function applyFieldErrors(form, errors) {
        Object.entries(errors).forEach(([fieldName, message]) => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            const group = field?.closest('.nxp-easy-form__group');

            if (!group) {
                return;
            }

            group.classList.add('nxp-easy-form__group--error');

            let errorEl = group.querySelector('.nxp-easy-form__error');
            if (!errorEl) {
                errorEl = document.createElement('p');
                errorEl.className = 'nxp-easy-form__error';
                errorEl.setAttribute('role', 'alert');
                group.appendChild(errorEl);
            }

            errorEl.textContent = message;

            if (field) {
                field.setAttribute('aria-invalid', 'true');
                if (!errorEl.id) {
                    errorEl.id = `${fieldName}-error`;
                }
                field.setAttribute('aria-describedby', errorEl.id);
            }
        });

        const firstInvalid = form.querySelector('[aria-invalid="true"]');
        if (firstInvalid) {
            setTimeout(() => firstInvalid.focus(), 100);
        }
    }

    function clearFieldErrors(form) {
        form.querySelectorAll('.nxp-easy-form__group--error').forEach(group => {
            group.classList.remove('nxp-easy-form__group--error');
        });

        form.querySelectorAll('.nxp-easy-form__error').forEach(errorEl => {
            errorEl.textContent = '';
        });

        form.querySelectorAll('[aria-invalid="true"]').forEach(field => {
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        });
    }

    const forms = document.querySelectorAll('.nxp-easy-form');

    forms.forEach((wrapper) => {
        if (wrapper.dataset.nxpEfBooted === '1') {
            return;
        }

        const form = wrapper.querySelector('.nxp-easy-form__form');
        if (!form) {
            return;
        }

        wrapper.dataset.nxpEfBooted = '1';

        const formId = Number(wrapper.dataset.formId || 0);
        const captchaConfig = {
            provider: wrapper.dataset.captchaProvider || 'none',
            siteKey: wrapper.dataset.captchaSiteKey || '',
        };
        const messages = form.querySelector('.nxp-easy-form__messages');
        const submitButton = form.querySelector(
            'button[type="submit"], .nxp-easy-form__button'
        );

        preloadCaptcha(captchaConfig.provider, captchaConfig.siteKey);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const successMessage =
                form.dataset.successMessage ||
                settings.successMessage ||
                'Thank you!';
            const errorMessage =
                form.dataset.errorMessage ||
                settings.errorMessage ||
                'Submission failed. Please try again.';

            if (messages) {
                messages.textContent = '';
                messages.classList.remove(
                    'nxp-easy-form__messages--error',
                    'nxp-easy-form__messages--success'
                );
            }
            clearFieldErrors(form);

            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const formData = new FormData(form);
                formData.append('formId', formId);

                const captchaResult = await attachCaptchaToken(
                    form,
                    formData,
                    captchaConfig
                );

                if (captchaResult !== true) {
                    const captchaMessage =
                        typeof captchaResult === 'string'
                            ? captchaResult
                            : settings.captchaFailedMessage;

                    if (messages) {
                        messages.textContent = captchaMessage;
                        messages.classList.add(
                            'nxp-easy-form__messages--error'
                        );
                    }
                    return;
                }

                const response = await fetch(`${restUrl}/submission`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const result = await response.json().catch(() => ({}));

                const actualData = result.data || result;
                const isSuccess =
                    response.ok && (result.success || actualData.success);

                if (!isSuccess) {
                    const errorFields =
                        actualData?.errors?.fields ||
                        result?.errors?.fields;

                    if (errorFields) {
                        clearFieldErrors(form);
                        applyFieldErrors(form, errorFields);
                    }

                    if (messages) {
                        messages.textContent =
                            actualData.message || result.message || errorMessage;
                        messages.classList.add(
                            'nxp-easy-form__messages--error'
                        );
                    }
                    return;
                }

                if (messages) {
                    messages.textContent =
                        actualData.message || result.message || successMessage;
                    messages.classList.add(
                        'nxp-easy-form__messages--success'
                    );
                }

                form.reset();
            } catch (error) {
                if (messages) {
                    messages.textContent =
                        settings.errorMessage ||
                        'Submission failed. Please try again.';
                    messages.classList.add('nxp-easy-form__messages--error');
                }
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    });
})();
