const globalBuilderSettings = () => window.nxpEasyForms?.builder || {};

/**
 * Build default API headers including CSRF protection.
 *
 * @param {Record<string, string>} extra     Additional headers to merge.
 * @param {{nonce?: string}} overrides  Optional override values.
 * @returns {Record<string, string>}
 */
export function buildApiHeaders(extra = {}, overrides = {}) {
    const source = {
        ...globalBuilderSettings(),
        ...overrides,
    };

    const headers = { Accept: "application/json", ...extra };

    if (source.nonce && !headers["X-CSRF-Token"]) {
        headers["X-CSRF-Token"] = source.nonce;
    }

    return headers;
}

/**
 * Wrapper around fetch that automatically attaches API headers and
 * rewrites relative component paths to the configured AJAX endpoint.
 *
 * @param {string} path          Relative API path or absolute URL.
 * @param {RequestInit} options  Fetch options.
 * @param {{nonce?: string, base?: string}} overrides Optional overrides.
 *
 * @returns {Promise<Response>}
 */
export function apiFetch(path, options = {}, overrides = {}) {
    const merged = {
        credentials: "same-origin",
        ...options,
    };

    merged.headers = buildApiHeaders(merged.headers || {}, overrides);

    let finalUrl = path;

    if (typeof path === "string" && !/^https?:\/\//i.test(path)) {
        const settings = globalBuilderSettings();
        const base = overrides.base || settings.restUrl;

        if (!base) {
            throw new Error("Missing AJAX endpoint base URL.");
        }

        const trimmed = path.replace(/^\/+/, "");
        const separator = base.includes("?") ? "&" : "?";

        finalUrl = trimmed
            ? `${base}${separator}path=${encodeURIComponent(trimmed)}`
            : base;
    }

    return fetch(finalUrl, merged);
}
