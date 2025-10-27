# NXP Easy Forms - Copilot AI Coding Instructions

## Project Context

This is a **Joomla 5+ component** being built as a migration of a WordPress plugin to Joomla.

**Reference Resources:**

-   **Original WordPress Plugin:** Available at `../nxp-easy-forms` (use as reference for migration logic)
-   **Migration Documentation:** See `JOOMLA_MIGRATION_GUIDELINE.md` for complete migration strategy and patterns
-   **Reference Component:** `REFERENCE_ONLY_unusedimagefinder_REFERENCE_ONLY/` - an older component for Joomla framework reference
-   **Local Test Instance:** Repository symlinked to `http://j5.loc` (Joomla 5 local instance at `/var/www/html/j5.loc`)
    -   Database credentials in `/var/www/html/j5.loc/configuration.php`
    -   Only modify this database for testing; do not touch other MySQL databases
-   **Associated Plugins:** Content plugin + webservices plugin for form submission API endpoints
-   **Documentation:** Check `README.md` for all major changes and feature updates

**Code Quality Rules:**

-   ✅ PSR-4 and PSR-12 compliant, Joomla 5+ modern standards
-   ✅ Zero deprecations allowed
-   ✅ Comprehensive docblocks on all methods
-   ✅ Security paramount: CSRF tokens, permission checks, `\defined('_JEXEC') or die;` always present
-   ✅ Admin UI: Vue.js 3 only (no vanilla JS)
-   ✅ Frontend UI: Vanilla JS only (no framework)
-   ✅ No git commits during development
-   ✅ SQL queries: Only against `/var/www/html/j5.loc` database

## Project Overview

A Joomla 5 component providing WordPress-equivalent form builder with submissions, email delivery, file uploads, and third-party integrations (Zapier, Slack, Mailchimp, Salesforce, HubSpot).

**PSR-4 Namespaces:**

-   `Joomla\Component\Nxpeasyforms\Administrator\` → `administrator/components/com_nxpeasyforms/src/`
-   `Joomla\Component\Nxpeasyforms\Site\` → `components/com_nxpeasyforms/src/`
-   `Joomla\Component\Nxpeasyforms\Api\` → `api/components/com_nxpeasyforms/src/`

## Email Delivery System

**Multiple Providers Supported:**

-   SendGrid, Postmark, Brevo, Amazon SES (SMTP), Custom SMTP, Mailpit, SMTP2GO

**Provider Configuration Strategy:**

-   **Global Defaults:** Set in component options (System → Manage Components → NXP Easy Forms → Options)
    -   Default provider, API keys, sender email (`mailfrom`), sender name (`sitename`)
-   **Form-Level Overrides:** Each form can override any provider setting
-   **Fallback Logic:** If form field is blank, uses component global default
-   **Implementation:** `EmailService::send()` handles provider delegation transparently

**Key File:** `src/Service/Email/EmailService.php` (handles all providers)

## Associated Plugins

Two companion plugins expose component functionality:

### Content Plugin (`plg_content_nxpeasyforms`)

-   Parses `{nxpeasyform id="123"}` shortcodes in articles and content
-   Renders forms with automatic asset loading
-   Enforces ACL checks (`core.view` permission)
-   Defers asset attachment to `onBeforeCompileHead` for efficiency

### Webservices Plugin (`plg_webservices_nxpeasyforms`)

-   Exposes form submission API endpoints to frontend (anonymous access)
-   Routes public submissions to `/api/v1/nxpeasyforms/submissions`
-   Allows `SubmissionController` to receive requests without authentication
-   Wires REST dispatcher to component's submission handler

## Architecture: Service-Oriented Layers

The codebase implements a **5-layer service architecture** around `SubmissionService` (684 lines, orchestration layer):

```
SubmissionService (orchestrator)
├── Repository (data access: FormRepository, SubmissionRepository)
├── Validation (FieldValidator: type coercion, required/email/url checks)
├── Security (CaptchaService, RateLimiter, IpHandler, EndpointValidator)
├── File Layer (FileUploader: MIME validation, image dimensions, UUID storage)
├── Rendering (TemplateRenderer: merge placeholders, MessageFormatter: Slack/Teams)
├── Integrations (IntegrationManager: Zapier, Slack, Teams, Mailchimp, Salesforce, HubSpot)
└── Email (EmailService: SendGrid, Postmark, Brevo, SES, Custom SMTP, Mailpit, SMTP2GO)
```

**Key Pattern:** Each layer is injected via DI container (`Factory::getContainer()`), allowing constructor-based dependency injection with fallbacks. Services use `final class` (immutable, no inheritance).

## Critical Developer Workflows

### Building Assets (Vue.js Admin + Frontend)

```bash
cd media/com_nxpeasyforms
npm install  # Install Vue 3, Pinia, SortableJS, Vite deps
npm run build  # Outputs: js/admin.js, js/frontend.js, css/admin.css, manifest.json
```

-   Assets written to `media/com_nxpeasyforms/` (not `build/`)
-   Vite manifests read by `AssetHelper::registerEntry()` (auto-hashing)
-   Admin app wrapped in IIFE to avoid global namespace pollution

### Running Tests

```bash
phpunit  # Runs tests/ directory (configured in phpunit.xml.dist)
```

-   Stubs in `tests/Stubs/` (Factory, Mailer, Uri, Text, etc.)
-   Tests use real service classes (no mocking business logic layers)
-   Example: `SubmissionServiceTest` constructs services with mock repositories

### Verifying Code

```bash
php vendor/bin/phpunit
composer lint  # If configured
```

## Project-Specific Conventions

### 1. **Submission Handling Flow** (Critical Path)

When a form is submitted via API or frontend:

1. **`SubmissionService::handle($formId, $data, $context)`** receives the request
2. Form validation (active? exists?)
3. Honeypot check (hidden field)
4. Token validation (skip if `context['skip_token_validation'] = true` for API)
5. Rate limiting (per IP, configurable)
6. CAPTCHA verification (if enabled: reCAPTCHA, Turnstile, FriendlyCaptcha)
7. **Field validation** (type-specific, email/URL/phone format)
8. File uploads (MIME check, image dimensions, UUID naming to `images/nxpeasyforms/`)
9. **Data sanitization** (removes HTML, escapes output)
10. Email notification (form email + admin emails, if enabled)
11. **Integration dispatch** (webhooks to Zapier, Slack, etc. via async queue)
12. Database storage (if enabled)
13. User registration (if form configured for registration)

**Key Files:**

-   Orchestrator: `src/Service/SubmissionService.php` (spans all layers)
-   Validation: `src/Service/Validation/FieldValidator.php` (type coercion)
-   Security: `src/Service/Security/RateLimiter.php`, `CaptchaService.php`
-   Email: `src/Service/Email/EmailService.php` (multi-provider support)
-   Integrations: `src/Service/Integrations/IntegrationManager.php` + dispatchers

### 2. **File Storage Convention**

Uploaded files stored in `/images/nxpeasyforms/` with UUID filenames (e.g., `a1b2c3d4-e5f6.jpg`).

-   MIME validation enforced (`FileValidator::validate()`)
-   Image dimension checks enforced (configurable max width/height)
-   Malicious file upload prevention (no executable files)

### 3. **Email Provider Integration**

Global defaults in component config (System → Manage Components → NXP Easy Forms → Options).

-   Form-level overrides merge with component defaults
-   Supported providers: SendGrid, Postmark, Brevo, Amazon SES (SMTP), Custom SMTP, Mailpit, SMTP2GO
-   When form field blank, fallback to component config (`mailfrom`, `sitename`)

**Key File:** `src/Service/Email/EmailService.php` (handles all providers transparently)

### 4. **Event System** (Joomla Events, Not WordPress Hooks)

Custom Joomla events mimic WordPress hook behavior:

-   **Action Events:** `onNxpEasyFormsAfterSubmission`, `onNxpEasyFormsUserRegistered`
-   **Filter Events:** `onNxpEasyFormsFilterSanitizedSubmission`, `onNxpEasyFormsFilterSlackPayload`

**Usage Pattern:**

```php
$dispatcher = Factory::getApplication()->getDispatcher();
$event = new Event('onNxpEasyFormsFilterSanitizedSubmission', ['data' => &$sanitised]);
$dispatcher->dispatch('onNxpEasyFormsFilterSanitizedSubmission', $event);
```

### 5. **Content Plugin Shortcode Rendering**

Forms embedded via `{nxpeasyform id="123"}` or `{nxpeasyform 123}` in articles.

-   Parsed by `plg_content_nxpeasyforms` (not WordPress `{nxp_easy_form 123}`)
-   Rendered by `FormRenderer` with automatic asset loading
-   ACL enforcement: `core.view` permission required

**Key File:** `plugins/content/nxpeasyforms/` (parses shortcodes, triggers rendering)

### 6. **API Response Format** (Web Services)

All API endpoints return JSON with consistent structure:

-   List: `{ "data": [...], "meta": {"total": 123, "limit": 20, "offset": 0} }`
-   Item: `{ "data": {...} }`
-   Errors: `{ "errors": [{"message": "...", "code": 404}] }`

**Key File:** `api/components/com_nxpeasyforms/src/Controller/` (API controllers)

### 7. **Database Schema** (Fixed)

Two core tables (unchanged from migration):

-   Forms table: id, title, fields JSON, settings JSON, active, timestamps
-   Submissions table: id, form_id, submission_uuid, data JSON, status, ip, user_agent, created_at

Migrations in `sql/updates/` follow pattern: `mysql.utf8.sql` → sequential versioning

### 8. **Dependency Injection Container Setup**

Registered in `administrator/components/com_nxpeasyforms/services/provider.php`.

**Pattern:**

```php
// Constructor-based injection with fallback to container
public function __construct(
    ?FormRepository $forms = null,
    ?FieldValidator $fieldValidator = null,
) {
    $container = Factory::getContainer();
    $this->forms = $forms ?? $container->get(FormRepository::class);
    $this->fieldValidator = $fieldValidator ?? $container->get(FieldValidator::class);
}
```

Allows easy testing: pass mocks as arguments, production code uses `Factory::getContainer()`.

## Integration Points & Cross-Component Communication

### Form Builder (Vue.js) ↔ Backend API

-   Admin builds forms in Vue SPA, saves JSON to forms table (fields + settings columns)
-   API endpoints: `POST /api/v1/nxpeasyforms/forms`, `PUT /api/.../forms/{id}`
-   Frontend loads form JSON via `FormRenderer`, renders HTML + JavaScript for submissions

### Content Plugin ↔ Shortcode Rendering

1. Content plugin detects `{nxpeasyform 123}`
2. Calls `FormRenderer::render($formId)`
3. Renderer fetches form from DB via `FormRepository`
4. Outputs HTML form + auto-loads CSS/JS assets
5. Frontend JS (`form-client.js`) handles submission → API call

### Submission Processing ↔ Third-Party Services

1. `SubmissionService::handle()` completes submission
2. `IntegrationManager::dispatch()` queues async jobs
3. `IntegrationQueue` (Psr16Cache-backed) stores pending jobs
4. Dispatcher classes (`SlackDispatcher`, `MailchimpDispatcher`, etc.) execute jobs
5. Each dispatcher constructs provider-specific payload, calls `HttpClient::post()`

### API ↔ Administrator Service Layer

-   `SubmissionController` (API) reuses admin `SubmissionService` via DI
-   Admin context passed via `$context['skip_token_validation'] = true` for API calls
-   Container bootstrapped if cold (cold start handling for API)

## Common Patterns to Follow

### 1. **Service Initialization Pattern**

Always support optional DI + container fallback:

```php
public function __construct(
    ?DependencyA $dep = null,
    ?DependencyB $dep = null
) {
    $container = Factory::getContainer();
    $this->depA = $dep ?? $container->get(DependencyA::class);
}
```

### 2. **Validation Pattern**

Use `ValidationResult` value object (not exceptions for validation failures):

```php
$result = $fieldValidator->validate($field, $value);
if (!$result->isValid()) {
    // Handle errors: $result->getErrors()
}
```

### 3. **Repository Query Pattern**

Repositories handle database interaction, return arrays (not Eloquent models):

```php
$form = $this->formRepository->find($id);  // Returns array or null
$submissions = $this->submissionRepository->findByFormId($formId);  // Returns array[]
```

### 4. **Exception Pattern**

Only throw exceptions for exceptional conditions (not validation failures):

```php
throw new SubmissionException('Rate limit exceeded', 429);
```

### 5. **Event Dispatching Pattern**

Use Joomla's `Event` + `DispatcherInterface`:

```php
$event = new Event('onNxpEasyFormsAfterSubmission', ['formId' => $id, 'data' => $data]);
$this->dispatcher->dispatch('onNxpEasyFormsAfterSubmission', $event);
```

## Asset Loading in Views

**Admin views:**

```php
// administrator/components/com_nxpeasyforms/tmpl/form/edit.php
use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('script', 'com_nxpeasyforms/admin.js', ['version' => 'auto', 'relative' => true]);
HTMLHelper::_('stylesheet', 'com_nxpeasyforms/admin.css', ['version' => 'auto', 'relative' => true]);
```

**Frontend:** Auto-loaded by `FormRenderer` during form rendering via `onBeforeCompileHead`.

## Testing Guidelines

-   **Unit Tests:** Test services directly with mock repositories
-   **Integration Tests:** Test full submission flow with real data layer
-   **Stubs:** Use `tests/Stubs/` for Joomla core classes (Factory, Mailer, etc.)
-   **Database:** Tests use configured database (reset between runs recommended)

Run tests: `phpunit` (configured via `phpunit.xml.dist`)

## Security Checklist

-   **CSRF:** `Session::checkToken()` (admin forms) or skip for API with `skip_token_validation`
-   **ACL:** `Factory::getUser()->authorise('core.view', 'com_nxpeasyforms')`
-   **Input:** `InputFilter::getInstance()->clean()` for all user input
-   **Output:** `htmlspecialchars()`, `Text::_()` for display
-   **File Upload:** MIME validation + extension whitelist + image dimension checks
-   **Rate Limiting:** Per-IP enforcement via `RateLimiter` + cache
-   **SSRF Protection:** `EndpointValidator` checks webhook URLs

## Troubleshooting

**"Failed to create model" error:** Ensure controllers use `Administrator` prefix for admin models.

**Assets not loading:** Run `npm run build` in `media/com_nxpeasyforms/` and check manifest exists.

**Email not sending:** Verify component config (System → Manage Components → NXP Easy Forms) has provider credentials set.

**Submissions not stored:** Check form config has `store_submissions` enabled in form settings.

**Webhooks failing:** Verify `EndpointValidator` allows domain (SSRF protection), check `IntegrationQueue` cache TTL.

## Key Files to Know

| File                                                                      | Purpose                                                   |
| ------------------------------------------------------------------------- | --------------------------------------------------------- |
| `src/Service/SubmissionService.php`                                       | Orchestration layer (entry point for submission handling) |
| `src/Service/Validation/FieldValidator.php`                               | Type-specific field validation                            |
| `src/Service/Email/EmailService.php`                                      | Multi-provider email delivery                             |
| `src/Service/Integrations/IntegrationManager.php`                         | Dispatcher factory + queue manager                        |
| `src/Service/Repository/FormRepository.php`                               | Form data access layer                                    |
| `media/com_nxpeasyforms/src/admin/main.js`                                | Vue admin SPA entry                                       |
| `media/com_nxpeasyforms/src/frontend/form-client.js`                      | Frontend form submission handler                          |
| `plugins/content/nxpeasyforms/nxpeasyforms.php`                           | Shortcode parser + renderer trigger                       |
| `api/components/com_nxpeasyforms/src/Controller/SubmissionController.php` | Public submission endpoint                                |

---

**Last Updated:** October 2025 | **Joomla Version:** 5.x | **PHP Version:** 8.0+
