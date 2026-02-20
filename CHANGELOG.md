# Changelog

All notable changes to NXP Easy Forms (Joomla) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.11] - 2026-02-19

### Changed

- **Joomla 5+/6+ legacy cleanup**: Consolidated all administrator form XML files into the standard `forms/` directory, removing the legacy `models/forms/` and `models/fields/` directories.
    - Moved `models/forms/form.xml` to `forms/form.xml`.
    - Moved `models/forms/submissions.xml` to `forms/filter_submissions.xml` (corrected filename to match Joomla's `ListModel::getFilterForm()` convention).
    - Deleted duplicate `models/forms/forms.xml` (identical to `forms/filter_forms.xml`).
    - Deleted legacy `models/fields/modal/form.php` (`JFormFieldModal_Form` wrapper); the modern namespaced `src/Field/Modal/FormField.php` is now the sole provider.
    - Updated menu layout XML (`components/com_nxpeasyforms/tmpl/form/default.xml`) `addfieldpath` from `models/fields` to `src/Field`.
    - Removed the entire `administrator/components/com_nxpeasyforms/models/` directory.

### Added

- **Submissions list filter bar**: Added search, form selector, and ordering controls to the administrator Submissions view using Joomla's standard searchtools layout, with `SubmissionsModel` state handling, `getStoreId()` cache keys, and parameterised query filters.
- **Orphaned submissions filter**: The submissions form filter dropdown now includes an "Orphaned (deleted form)" option that shows submissions whose parent form has been deleted, making it easy to find and bulk-delete orphaned records.
- **Form publish/unpublish toggle**: Added clickable status toggle in the forms list view using Joomla's `jgrid.published` helper, with Publish/Unpublish toolbar buttons gated by `core.edit.state` permission. The `FormTable` column alias maps the `active` column to Joomla's standard `published` convention so the inherited `AdminController::publish()` works without custom controller code.

### Fixed

- **Submissions filter "Clear" button required two clicks**: Filter default values used non-empty strings (`"all"`, `"0"`) that Joomla's searchtools JS did not recognise as cleared state. Changed all filter defaults to empty string so a single Clear click resets correctly.
- **Export re-triggered on filter change**: After exporting submissions the hidden `task` field retained `submissions.export`, causing subsequent filter changes to re-fire the export. Added a submit listener that resets the task field after an export download begins.
- **Submissions toolbar layout**: The searchtools container was constrained to `col-lg-6`, cramping filter controls on wide screens. Changed to `col-12` to match the Forms view.

### Security

- **SSRF fail-open on unresolvable hosts**: `EndpointValidator::validate()` accepted webhook endpoints whose hostname could not be resolved to any IP address, because the empty-array IP validation loop was a no-op. Now returns `null` (reject) when DNS resolution yields no records.
- **CSRF origin fail-open for browser requests**: `SubmissionController::isValidOrigin()` returned `true` when both `Origin` and `Referer` headers were absent, allowing cross-site browser requests to bypass origin validation. Now fails closed (`return false`) for non-API browser requests missing both headers; legitimate API clients already bypass origin checks via the `isApiClient()` gate.

### Removed

- **Submission status column and filter**: Removed the non-functional status column, status filter dropdown, and "Default submission status" component option from the Submissions view. The database column is retained for future use when full status CRUD is implemented.

## [1.0.10] - 2026-02

### Added

- New Joomla site module `mod_nxpeasyforms` for rendering a selected active NXP Easy Form in any module position, built with the service-based module architecture (`services/provider.php`) and no legacy `mod_nxpeasyforms.php` entry file.
- Release packaging now includes `mod_nxpeasyforms` in `pkg_nxpeasyforms` ZIP builds.

### Fixed

- Form builder toolbar now shows `Close` while editing an existing form and keeps `Cancel` only for unsaved new forms.
- Registered plugin language files in manifests for `plg_content_nxpeasyforms` and `plg_webservices_nxpeasyforms` so description constants load reliably during installation and extension management.
- Microsoft Teams is now fully disabled in form integrations: the builder no longer exposes Teams settings, backend integration dispatch no longer registers a Teams handler, and legacy `integrations.teams` payload keys are removed during options normalisation.

## [1.0.9] - 2025-01-09

### Security

- Hardened encryption key handling with RuntimeException for unconfigured secrets
- CSS injection prevention with `sanitizeCustomCss()` method
- Upload directory protection with .htaccess, web.config, and index.html
- SQL injection protection in `SubmissionRepository::findByIds()`
- API CSRF protection with origin/referer validation
- Enhanced file extension validation with double extension detection
- IP header spoofing protection with trusted proxy configuration
- API error response hardening to prevent information leakage

### Added

- Country/State field types with dynamic state loading
- Country/State API endpoints for frontend population
- Encrypted CAPTCHA secrets storage
- Administrator AJAX refactor with service-oriented routing
- Modal form selector for menu items
- Custom form aliases for SEF-friendly URLs
- 9 email delivery providers (SendGrid, Mailgun, Postmark, Brevo, Amazon SES, SMTP, etc.)

### Fixed

- Frontend validation error display
- File upload handling for Joomla 5.4.0 compatibility
- Joomla Article integration form path loading
- Single Form menu SEF routing
- Builder defaults for new forms (store/email enabled by default)
- Frontend CAPTCHA handling with provider-specific scripts
- Featured image propagation for article submissions

## [1.0.0] - 2025-09-22

### Added

- Initial Joomla 5 port of NXP Easy Forms
- Vue.js 3 drag-and-drop form builder
- 15 pre-built form templates
- 12 field types (Text, Email, Telephone, Password, Textarea, Select, Radio, Checkbox, File Upload, Date Picker, Hidden, Custom Text)
- Security features: Honeypot, CSRF, rate limiting, CAPTCHA providers
- Privacy controls: IP anonymisation, auto-deletion
- Email delivery with multiple providers
- User registration forms with email verification
- Integrations: Zapier, Make, Slack, Teams, Mailchimp, Salesforce, HubSpot
- Joomla Article integration with ACF-like field mapping
- Content plugin for shortcode rendering
- SEF routing with custom router
- Async integration queue for background processing

[Unreleased]: https://github.com/nexusplugins/com_nxpeasyforms/compare/v1.0.10...HEAD
[1.0.10]: https://github.com/nexusplugins/com_nxpeasyforms/compare/v1.0.9...v1.0.10
[1.0.9]: https://github.com/nexusplugins/com_nxpeasyforms/compare/v1.0.0...v1.0.9
[1.0.0]: https://github.com/nexusplugins/com_nxpeasyforms/releases/tag/v1.0.0
