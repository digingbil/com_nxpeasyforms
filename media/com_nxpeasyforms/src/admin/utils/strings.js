/**
 * Safely trim a value, returning empty string if null/undefined
 * @param {*} value - Value to trim
 * @returns {string} Trimmed string
 */
export function safeTrim(value) {
    if (value === null || value === undefined) {
        return "";
    }
    return typeof value === "string" ? value.trim() : String(value).trim();
}

/**
 * Check if value is a plain object (not array, not null)
 * @param {*} value - Value to check
 * @returns {boolean}
 */
export function isObject(value) {
    return value !== null && typeof value === "object" && !Array.isArray(value);
}

/**
 * Generate a random row ID
 * @returns {string} Random ID
 */
export function createRowId() {
    return Math.random().toString(36).slice(2, 10);
}
