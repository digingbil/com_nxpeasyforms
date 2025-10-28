# NXP Easy Forms (Joomla Component)

This repository contains the Joomla 5 port of the NXP Easy Forms WordPress plugin. The notes below summarise the latest migration work and highlight behaviour differences introduced while matching WordPress features to Joomla best-practices.

## Recent Changes

### Bug Fixes
- Restored the administrator form builder boot sequence so existing forms hydrate their saved title, fields, and settings immediately instead of flashing empty state or "Untitled form".
- Normalised the content plugin so it now parses `{nxpeasyform ...}` shortcodes coming from both objects and raw strings (articles, custom modules, custom fields), ensuring rendered markup replaces the placeholder across every Joomla content source while keeping asset loading idempotent.
- Corrected the administrator controllers to request models via Joomla's `Administrator` prefix, eliminating the "Failed to create model" error when deleting or otherwise acting on forms and submissions.
- Ensured frontend ACL defaults allow public rendering of shortcode-driven forms, restored the menu "Single Form" selector, and hardened duplication to work even when the DI container has not pre-registered repository services.
- Removed the redundant bootstrap PHP entry file now that the DI service provider instantiates the plugin, relying solely on the manifest namespace for PSR-4 loading.
- Bootstrapped the component service provider inside the administrator AJAX controller so test-email and other builder requests always have access to registered services without relying on global dispatcher state.
- **Fixed Joomla Article integration form path loading**: Updated `JoomlaArticleDispatcher` to use `Form::addFormPath()` static method instead of the non-existent `ArticleModel::addFormPath()` instance method, resolving "Call to undefined method" errors when creating articles from form submissions in Joomla 5.4.0+.
- **Fixed frontend validation error display**: Updated `frontend.joomla.js` to properly extract and display field-level validation errors from the nested `JsonResponse` wrapper structure (`data.errors.fields`), ensuring error messages now appear next to form fields in `.nxp-easy-form__error` elements with proper ARIA attributes.
- **Fixed file upload handling**: Replaced deprecated `Folder::exists()` with native `is_dir()` in `FileUploader` for Joomla 5.4.0 compatibility.
- **Fixed Joomla Article integration author modes**: Added support for 'none' author mode in `JoomlaArticleDispatcher::resolveAuthorId()`, allowing articles to be created without an assigned author (created_by = 0).
- **Removed deprecated meta fields from Joomla Article integration**: Cleaned up `metadesc` and `metakey` fields from article creation payload as these are no longer used in Joomla content articles.
- **Fixed duplicate variable declaration**: Resolved JavaScript error "can't access lexical declaration 'o' before initialization" in `formStore.js` by renaming duplicate `payload` variable to `responseData`.
- **Fixed test email recipient resolution**: Updated email settings retrieval in `AjaxController` to handle nested component params structure and corrected recipient resolution logic in `FormSettingsModal.vue` to check `use_global_recipient` flag before local field values.
- **Restored script options for builder Vue app**: Injected `initialData` and `lang` script options so editing existing forms no longer relies on an AJAX fetch and translations display correctly.
- **Improved developer notices**: Replaced the WordPress `notice` markup with Bootstrap-flavoured alerts in the administrator builder (FA6 icons, close buttons) to match Joomla styling.
- **Prevented duplicate forms on save**: Ensured the builder always posts the numeric form id when saving so empty-field saves update the current form rather than creating a new one.
- **Frontend CAPTCHA handling**:
  - Added `data-captcha-provider` / `data-captcha-site-key` attributes to rendered forms so the frontend knows which provider/keys to use.
  - Rebuilt `frontend.joomla.js` to load the appropriate provider script (reCAPTCHA v3, Cloudflare Turnstile, Friendly Captcha), request tokens, populate `_nxp_captcha_token`, and surface provider-specific error messages when verification fails.
- **Featured image propagation**: Joomla article submissions now copy the uploaded featured image into `images/nxpeasyforms/` and populate the article's intro/full image metadata automatically, so the image appears on the created article without manual edits.
- **Featured image mapping persistence**: Normalised Joomla article integration defaults so the featured image, alt text, and caption field selections survive subsequent edits and legacy configurations continue to hydrate correctly.
- **Featured image mapping controls**: Added dedicated mapping inputs (file selector plus alt/caption fields) and adjusted the dispatcher to pass the structured `images` payload expected by `com_content`, ensuring the intro image metadata is retained without the post-save shim.
- Restored the administrator form builder boot sequence so existing forms hydrate their saved title, fields, and settings immediately instead of flashing empty state or "Untitled form".
- Normalised the content plugin so it now parses `{nxpeasyform ...}` shortcodes coming from both objects and raw strings (articles, custom modules, custom fields), ensuring rendered markup replaces the placeholder across every Joomla content source while keeping asset loading idempotent.
- Corrected the administrator controllers to request models via Joomla's `Administrator` prefix, eliminating the "Failed to create model" error when deleting or otherwise acting on forms and submissions.
- Ensured frontend ACL defaults allow public rendering of shortcode-driven forms, restored the menu "Single Form" selector, and hardened duplication to work even when the DI container has not pre-registered repository services.
- Removed the redundant bootstrap PHP entry file now that the DI service provider instantiates the plugin, relying solely on the manifest namespace for PSR-4 loading.
- Bootstrapped the component service provider inside the administrator AJAX controller so test-email and other builder requests always have access to registered services without relying on global dispatcher state.

### Content Plugin Rendering
- Fixed the `{nxpeasyform 123}` content plugin so shortcodes now render forms instead of leaking raw placeholders.
- Added ACL enforcement (`core.view`) and robust parsing for shortcodes such as `{nxpeasyform id=123}`.
- Taught the content plugin to resolve forms directly via the repository, auto-register the component namespaces when needed, and honour explicit ACL denies while defaulting to public rendering when no restriction is configured.
- Deferred asset attachment to `onBeforeCompileHead`, switched to WebAsset-aware loading with legacy fallbacks, and guaranteed the frontend CSS/JS bundle from `media/com_nxpeasyforms` is injected exactly once per request.
- Registered a dedicated webservices plugin so anonymous clients can POST to `v1/nxpeasyforms/submissions`, wiring the API router to the component’s submission controller.
- Added an API component service provider/extension so the REST dispatcher can resolve `SubmissionsController` under `api/components/com_nxpeasyforms`.

### Email Delivery Providers
- Expanded the email service to support Mailgun, Postmark, Brevo, Amazon SES (SMTP), Custom SMTP, Mailpit, SendGrid and SMTP2GO.
- Added configuration fields under *System → Manage Components → NXP Easy Forms → Options* for provider credentials and connection details.
- Updated the form builder defaults, administrator Vue app, and AJAX normalisation to handle the new provider payloads.
- Normalised component parameters so the email service now reads the global provider directly from the options without the legacy nested `params` wrapper, meaning forms that stick to the defaults inherit the component-wide provider automatically (e.g. Mailpit). When form-level recipients or sender details are blank, we now fall back to Joomla’s `mailfrom`/`sitename` to guarantee deliverable messages and avoid silent drops.
- Honoured the builder’s “Use global …” toggles, ensuring form-level delivery, from-name, from-email, and recipient settings inherit the component configuration when those switches are enabled while still allowing bespoke overrides when they are not.
- Improved the administrator “Send test email” flow so success and failure notifications surface the translated message returned by the backend rather than raw HTTP status codes.

### Administrator Builder UX
- **Hidden sidebar in form builder**: Updated `Form/HtmlView.php` to hide the Joomla administrator sidebar (`#sidebarmenu`) when editing forms in the builder, providing more screen real estate and a cleaner editing experience.
- **Updated post-submission template**: Removed "Custom URL Alias" and "Author Attribution" fields from the post-submission form template, streamlining the article submission workflow.
- **Updated Joomla Article integration defaults**: Changed default author mode from "Anonymous" to "No user" (value: 'none'), removed meta description and meta keywords field mappings from integration settings and form defaults.
- Injected Vue script options as `lang`/`initialData`, bringing the Joomla builder into parity with the WordPress app and avoiding unnecessary AJAX fetches when editing existing forms.
- Added a dedicated callout for the "Send email notifications" switch to visually separate the primary toggle from advanced delivery checkboxes in the settings modal.

### Internationalisation Utilities
- Replaced legacy `i18n` helpers with a new `@/utils/translate` helper that falls back to Joomla’s `Text` API and WordPress’ `wp.i18n` when available.
- Renamed all script option payloads from `i18n` to `lang` to avoid legacy naming conflicts.

### Frontend Site View & Routing
- Implemented a site `Form` view with ACL checks, dynamic document titles, and automatic asset loading.
- Added a PSR-12 compliant SEF router that generates URLs such as `/form/23-contact-us` and parses `id` + slug pairs.
- Introduced a menu layout (`components/com_nxpeasyforms/tmpl/form/default.xml`) so a “Single Form” menu item can be created via Joomla menus.
- Added a site `Forms` field for menu parameters and ensured inactive forms surface clearly.

### Form Duplication (Administrator)
- Added a `FormRepository::duplicate()` helper with event support (`onNxpEasyFormsFilterDuplicateTitle`), preserving JSON configurations.
- Introduced a “Duplicate” toolbar button in the forms list (`forms` view) with ACL checks and success/error messaging.
- Added the task handler in `FormsController` so selected forms can be duplicated in bulk from the administrator view.

### Submission API
- Updated the public `SubmissionController` so JSON responses now carry the correct HTTP status codes (404 for missing forms, 4xx for validation issues, etc.) and rely on a shared responder that closes the API application cleanly. The controller also skips CSRF token checks for API calls (still enforced on the Joomla frontend) and reuses the administrator services by bootstrapping domain providers when the container is cold.

## Development Notes

- **Assets:** The admin Vue bundle (`media/com_nxpeasyforms`) now consumes `@/utils/translate`. Re-run `npm install` (if needed) and `npm run build` inside `media/com_nxpeasyforms` after pulling these changes.
- **Frontend JavaScript:** The Joomla version uses `js/frontend.joomla.js` (hand-written, ~5.4KB) instead of the Vite-built `js/frontend.js` (~15KB). The Joomla-specific file is loaded by `components/com_nxpeasyforms/src/View/Form/HtmlView.php` and `plugins/content/nxpeasyforms/src/Extension/Nxpeasyforms.php`. The Vite-built version (`src/frontend/form-client.js`) remains from the WordPress codebase but is not referenced in Joomla. Both files now include proper validation error handling for field-level error display.
- **Events:** Custom events continue to mirror WordPress hook behaviour. New duplication logic exposes `onNxpEasyFormsFilterDuplicateTitle` for plugins.
- **Routing:** Friendly URLs rely on the new router class; ensure caches are cleared and menus rebuilt after installation.

For any further migration tasks, consult `JOOMLA_MIGRATION_GUIDELINE.md` and mirror missing WordPress features while following Joomla’s ACL, routing, and DI conventions showcased above.
