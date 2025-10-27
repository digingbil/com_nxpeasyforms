(() => {
    const settings = window.nxpEasyFormsFrontend || {};
    const restUrl = settings.restUrl || '';

    if (!restUrl) {
        return;
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
        const messages = form.querySelector('.nxp-easy-form__messages');
        const submitButton = form.querySelector('button[type="submit"], .nxp-easy-form__button');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const successMessage = form.dataset.successMessage || settings.successMessage || 'Thank you!';
            const errorMessage = form.dataset.errorMessage || settings.errorMessage || 'Submission failed. Please try again.';

            if (messages) {
                messages.textContent = '';
                messages.classList.remove('nxp-easy-form__messages--error', 'nxp-easy-form__messages--success');
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            try {
                const formData = new FormData(form);
                formData.append('formId', formId);
                const response = await fetch(`${restUrl}/submission`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const result = await response.json().catch(() => ({}));

                if (!response.ok || !result.success) {
                    if (messages) {
                        messages.textContent = result.message || errorMessage;
                        messages.classList.add('nxp-easy-form__messages--error');
                    }
                    return;
                }

                if (messages) {
                    messages.textContent = result.message || successMessage;
                    messages.classList.add('nxp-easy-form__messages--success');
                }

                form.reset();
            } catch (error) {
                if (messages) {
                    messages.textContent = settings.errorMessage || 'Submission failed. Please try again.';
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
