/**
 * Script loader for captcha providers
 */
export class ScriptLoader {
    constructor() {
        this.cache = new Map();
    }

    load(src) {
        if (!this.cache.has(src)) {
            this.cache.set(
                src,
                new Promise((resolve, reject) => {
                    const script = document.createElement("script");
                    script.src = src;
                    script.async = true;
                    script.defer = true;
                    script.onload = () => resolve();
                    script.onerror = () =>
                        reject(new Error(`Failed to load ${src}`));
                    document.head.appendChild(script);
                }),
            );
        }

        return this.cache.get(src);
    }

    loadCaptchaScript(provider, siteKey) {
        const loaders = {
            recaptcha_v3: (key) =>
                this.load(
                    `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(key)}`,
                ),
            turnstile: () =>
                this.load(
                    "https://challenges.cloudflare.com/turnstile/v0/api.js",
                ),
            friendlycaptcha: () =>
                this.load(
                    "https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.8/widget.min.js",
                ),
        };

        const loader = loaders[provider];
        if (!loader) {
            return Promise.resolve();
        }

        if (provider === "recaptcha_v3" && !siteKey) {
            return Promise.resolve();
        }

        return loader(siteKey);
    }
}
