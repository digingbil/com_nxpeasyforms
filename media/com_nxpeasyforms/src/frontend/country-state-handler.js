/**
 * Country and State field handler
 * Populates dropdowns and manages state field dependencies
 */
export class CountryStateHandler {
    constructor(restUrl) {
        this.restUrl = restUrl;
        this.cache = {
            countries: {},
            states: {},
        };
        // Lightweight debug gate - enabled by setting `window.nxpEasyFormsFrontend.debug = true` in dev
        this._debug =
            typeof window !== 'undefined' &&
            Boolean(
                window.nxpEasyFormsFrontend &&
                    window.nxpEasyFormsFrontend.debug === true
            );
        this._dbg = (...args) => {
            if (this._debug && console && console.debug) {
                console.debug(...args);
            }
        };
        this.init();
    }

    init() {
        // Wait for DOM to be fully ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () =>
                this.initFields()
            );
        } else {
            this.initFields();
        }
    }

    initFields() {
        // Initialize all country fields
        const countryFields = document.querySelectorAll(
            '.nxp-easy-form__country'
        );
        // Debug: number of country fields found
        countryFields.forEach((select) => this.initCountryField(select));

        // Initialize all state fields
        const stateFields = document.querySelectorAll('.nxp-easy-form__state');
        // Debug: number of state fields found
        stateFields.forEach((select) => this.initStateField(select));
    }

    async initCountryField(select) {
        const mode = select.dataset.wooMode || 'all';
        const placeholder =
            select.querySelector('option[value=""]')?.textContent ||
            'Select a country';

        // Debug: country field initialization

        try {
            const countries = await this.fetchCountries(mode);
            // Debug: countries fetched count
            this.populateCountrySelect(select, countries, placeholder);

            // Listen for changes to update linked state fields
            select.addEventListener('change', () =>
                this.handleCountryChange(select)
            );
        } catch (error) {
            this._dbg('[Country] Failed to load countries:', error);
        }
    }

    async initStateField(select) {
        let countryFieldName = select.dataset.countryField;
        this._dbg(
            '[State] Initializing field, linked to country field:',
            countryFieldName
        );

        // Find the parent form first
        const form = select.closest('form');
        if (!form) {
            console.warn('[State] No parent form found');
            return;
        }

        // If not explicitly linked, try to auto-detect the country field
        if (!countryFieldName) {
            // Strategy 1: if exactly one country field exists in the form, use it
            const countriesInForm = form.querySelectorAll(
                '.nxp-easy-form__country'
            );
            if (countriesInForm.length === 1) {
                const onlyCountry = countriesInForm[0];
                countryFieldName = onlyCountry.getAttribute('name') || '';
                if (countryFieldName) {
                    select.dataset.countryField = countryFieldName;
                    // Debug: auto-linked to sole country field
                }
            }

            // Strategy 2: match name pattern: foo_state -> foo_country
            if (!countryFieldName) {
                const stateName = select.getAttribute('name') || '';
                const match = stateName.match(/^(.*)_state$/);
                if (match && match[1]) {
                    const candidate = `${match[1]}_country`;
                    const candidateEl = form.querySelector(
                        `[name="${candidate}"]`
                    );
                    if (candidateEl) {
                        countryFieldName = candidate;
                        select.dataset.countryField = countryFieldName;
                        // Debug: auto-linked by name pattern
                    }
                }
            }
        }

        if (!countryFieldName) {
            console.warn(
                '[State] No country field specified in data-country-field and auto-link failed'
            );
            return;
        }

        // Find the linked country field
        const countryField = form.querySelector(`[name="${countryFieldName}"]`);
        if (!countryField) {
            console.warn('[State] Country field not found:', countryFieldName);
            return;
        }

        // Debug: linked country field found

        // If country already has a value, load states
        if (countryField.value) {
            // Debug: country already selected for state init
            await this.loadStatesForCountry(select, countryField.value);
        }
    }

    async handleCountryChange(countrySelect) {
        const countryCode = countrySelect.value;
        // Debug: country changed

        const form = countrySelect.closest('form');
        if (!form) {
            console.warn('[Country] No parent form found');
            return;
        }

        // Find all state fields linked to this country field.
        // Include both select state fields and converted inputs that carry data-original-select-html.
        const countryFieldName = countrySelect.getAttribute('name');
        const linkedStateFields = form.querySelectorAll(
            `.nxp-easy-form__state[data-country-field="${countryFieldName}"]`
        );
        const linkedConvertedInputs = form.querySelectorAll(
            `input[data-original-select-html][data-country-field="${countryFieldName}"]`
        );
        let linked = [...linkedStateFields, ...linkedConvertedInputs];

        // Fallback: if there is exactly one state-like field and one country field, link them
        if (!linked.length) {
            const statesSelects = form.querySelectorAll(
                '.nxp-easy-form__state'
            );
            const stateConvertedInputs = form.querySelectorAll(
                'input[data-original-select-html]'
            );
            const countriesInForm = form.querySelectorAll(
                '.nxp-easy-form__country'
            );
            const totalStateLike =
                statesSelects.length + stateConvertedInputs.length;
            if (totalStateLike === 1 && countriesInForm.length === 1) {
                const soleState = statesSelects[0] || stateConvertedInputs[0];
                soleState.dataset.countryField = countryFieldName || '';
                linked = [soleState];
                // Debug: auto-linked sole state field
            }
        }

        // Debug: found linked state fields

        linked.forEach(async (stateEl) => {
            if (countryCode) {
                // Debug: loading states for country
                await this.loadStatesForCountry(stateEl, countryCode);
            } else {
                this.clearStateField(stateEl);
            }
        });
    }

    async loadStatesForCountry(stateSelect, countryCode) {
        const placeholder =
            stateSelect.querySelector('option[value=""]')?.textContent ||
            'Select a state';
        const allowText = stateSelect.dataset.allowText === '1';

        try {
            const states = await this.fetchStates(countryCode);

            if (Object.keys(states).length > 0) {
                // Country has states - ensure element is a <select> first, then populate
                const selectEl = this.ensureSelectMode(stateSelect);
                this.populateStateSelect(selectEl, states, placeholder);
            } else if (allowText) {
                // No states - convert to text input if allowed
                this.convertToTextInput(stateSelect);
            } else {
                // No states and text input not allowed - disable
                this.clearStateField(stateSelect);
                stateSelect.disabled = true;
            }
        } catch (error) {
            this._dbg('Failed to load states:', error);
        }
    }

    async fetchCountries(mode = 'all') {
        const cacheKey = mode;

        if (this.cache.countries[cacheKey]) {
            return this.cache.countries[cacheKey];
        }

        const response = await fetch(
            `${this.restUrl}/utility/countries?mode=${mode}`
        );
        const data = await response.json();

        if (data.success && data.countries) {
            this.cache.countries[cacheKey] = data.countries;
            return data.countries;
        }

        return {};
    }

    async fetchStates(countryCode) {
        const code = String(countryCode || '').toUpperCase();

        if (!code || code.length !== 2) {
            return {};
        }

        if (this.cache.states[code]) {
            return this.cache.states[code];
        }

        const response = await fetch(`${this.restUrl}/utility/states/${code}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            this._dbg(
                '[State] HTTP error when fetching states:',
                response.status,
                response.statusText
            );
            return {};
        }

        const data = await response.json().catch(() => ({}));

        if (
            data &&
            data.success &&
            data.states &&
            typeof data.states === 'object'
        ) {
            this.cache.states[code] = data.states;
            return data.states;
        }

        return {};
    }

    populateCountrySelect(select, countries, placeholder) {
        // Clear existing options except placeholder
        select.innerHTML = `<option value="">${placeholder}</option>`;

        // Add countries
        Object.entries(countries).forEach(([code, name]) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = name;
            select.appendChild(option);
        });
    }

    populateStateSelect(select, states, placeholder) {
        // Clear existing options
        select.innerHTML = `<option value="">${placeholder}</option>`;

        // Add states
        Object.entries(states).forEach(([code, name]) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = name;
            select.appendChild(option);
        });

        select.disabled = false;
    }

    clearStateField(select) {
        if (!select) return;
        if (select.tagName === 'INPUT') {
            // For converted inputs, clear the value
            select.value = '';
            return;
        }
        const placeholder =
            select.querySelector('option[value=""]')?.textContent ||
            'Select a state';
        select.innerHTML = `<option value="">${placeholder}</option>`;
        select.value = '';
    }

    convertToTextInput(select) {
        // Create a text input to replace the select
        const input = document.createElement('input');
        input.type = 'text';
        input.name = select.name;
        input.id = select.id;
        input.className = select.className.replace(
            'nxp-easy-form__state',
            'nxp-easy-form__input'
        );
        input.placeholder =
            select.querySelector('option[value=""]')?.textContent || '';
        input.required = select.hasAttribute('required');

        // Store original select for potential conversion back
        input.dataset.originalSelectHtml = select.outerHTML;
        // Preserve linkage and behavior flags on the converted input
        if (select.dataset.countryField) {
            input.dataset.countryField = select.dataset.countryField;
        }
        if (select.dataset.allowText) {
            input.dataset.allowText = select.dataset.allowText;
        }

        // Replace select with input
        select.parentNode.replaceChild(input, select);
    }

    ensureSelectMode(element) {
        // If element is currently a text input (from previous country change), convert back to select
        if (element.tagName === 'INPUT' && element.dataset.originalSelectHtml) {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = element.dataset.originalSelectHtml;
            const select = wrapper.firstElementChild || wrapper.firstChild;
            element.parentNode.replaceChild(select, element);
            return select;
        }
        return element;
    }
}
