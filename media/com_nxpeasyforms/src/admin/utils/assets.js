const DEFAULT_MEDIA_BASE = "/media/com_nxpeasyforms/";

/**
 * Resolve a public URL to an asset stored in the media package.
 *
 * Falls back to a sane default when the Joomla helpers are not available,
 * ensuring the UI remains functional during SSR or tests.
 *
 * @param {string} relativePath Relative path from the media root (e.g. "assets/icons/icon.svg").
 *
 * @returns {string} A fully-qualified (or site-root relative) URL to the asset.
 */
export function getMediaAssetUrl(relativePath) {
    const sanitizedPath = String(relativePath || "").replace(/^\/+/, "");

    if (typeof window === "undefined") {
        return `${DEFAULT_MEDIA_BASE}${sanitizedPath}`;
    }

    const nxp = window.nxpEasyForms;

    if (nxp && typeof nxp.mediaBase === "string" && nxp.mediaBase !== "") {
        const base = nxp.mediaBase.replace(/\/+$/, "");

        return `${base}/${sanitizedPath}`;
    }

    if (window.Joomla && typeof window.Joomla.getOptions === "function") {
        const paths = window.Joomla.getOptions("system.paths") || {};
        const root = typeof paths.root === "string" ? paths.root : "";
        const rootFull = typeof paths.rootFull === "string" ? paths.rootFull : "";
        const baseRoot = rootFull || root;

        if (baseRoot !== "") {
            const normalizedRoot = baseRoot.replace(/\/+$/, "");
            const prefix = normalizedRoot === "" ? "" : normalizedRoot;

            return `${prefix}/media/com_nxpeasyforms/${sanitizedPath}`;
        }
    }

    return `${DEFAULT_MEDIA_BASE}${sanitizedPath}`;
}
