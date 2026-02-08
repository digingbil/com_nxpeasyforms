# Changelog

All notable changes to NXP Easy Forms (Joomla) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- New Joomla site module `mod_nxpeasyforms` for rendering a selected active NXP Easy Form in any module position, built with the service-based module architecture (`services/provider.php`) and no legacy `mod_nxpeasyforms.php` entry file.
- Release packaging now includes `mod_nxpeasyforms` in `pkg_nxpeasyforms` ZIP builds.

### Fixed

- Form builder toolbar now shows `Close` while editing an existing form and keeps `Cancel` only for unsaved new forms.
- Registered plugin language files in manifests for `plg_content_nxpeasyforms` and `plg_webservices_nxpeasyforms` so description constants load reliably during installation and extension management.

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

[Unreleased]: https://github.com/nexusplugins/com_nxpeasyforms/compare/v1.0.9...HEAD
[1.0.9]: https://github.com/nexusplugins/com_nxpeasyforms/compare/v1.0.0...v1.0.9
[1.0.0]: https://github.com/nexusplugins/com_nxpeasyforms/releases/tag/v1.0.0
