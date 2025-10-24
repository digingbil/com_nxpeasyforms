export const TEXT_DOMAIN = "nxp-easy-forms";

export function __(text, contextDomain = TEXT_DOMAIN) {
    if (window.wp?.i18n?.__) {
        return window.wp.i18n.__(text, contextDomain);
    }

    return text;
}

export function _x(text, context, contextDomain = TEXT_DOMAIN) {
    if (window.wp?.i18n?._x) {
        return window.wp.i18n._x(text, context, contextDomain);
    }

    return text;
}

export function html__(text, contextDomain = TEXT_DOMAIN) {
    if (window.wp?.i18n?.html__) {
        return window.wp.i18n.html__(text, contextDomain);
    }

    return text;
}

export function html_x(text, context, contextDomain = TEXT_DOMAIN) {
    if (window.wp?.i18n?.html_x) {
        return window.wp.i18n.html_x(text, context, contextDomain);
    }

    return text;
}

export function sprintf(format, ...args) {
    if (window.wp?.i18n?.sprintf) {
        return window.wp.i18n.sprintf(format, ...args);
    }

    return format;
}
