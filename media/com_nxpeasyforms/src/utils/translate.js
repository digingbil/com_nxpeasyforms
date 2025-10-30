/**
 * Lightweight translation helper that falls back to Joomla globals when available.
 *
 * @param {string} text - Source string to translate.
 * @param {string} [domain='com_nxpeasyforms'] - Translation domain/context.
 * @returns {string} Translated string when available, otherwise the original text.
 */
export function __(text, domain = 'com_nxpeasyforms') {
    if (typeof text !== 'string') {
        return text;
    }

    if (typeof window !== 'undefined') {
        if (window.Joomla?.Text) {
            const translated = window.Joomla.Text._(text);

            if (translated && translated !== text) {
                return translated;
            }
        }

        const wp = window.wp;

        if (wp?.i18n?.__) {
            return wp.i18n.__(text, domain);
        }
    }

    return text;
}
