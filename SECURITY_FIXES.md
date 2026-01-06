# Security Fixes Guide - NXP Easy Forms

This document outlines security vulnerabilities identified during a comprehensive security audit of the NXP Easy Forms Joomla component. Each issue includes severity, location, impact, and detailed remediation steps.

**Audit Date:** January 2026
**Component Version:** 1.0.5
**Auditor:** Security Review

---

## Table of Contents

1. [Critical Issues](#critical-issues)
   - [1.1 Weak Encryption Key Fallback](#11-weak-encryption-key-fallback)
   - [1.2 CSS Injection Vulnerability](#12-css-injection-vulnerability)
   - [1.3 Missing Upload Directory Protection](#13-missing-upload-directory-protection)
2. [High Severity Issues](#high-severity-issues)
   - [2.1 SQL Injection Risk in IN Clause](#21-sql-injection-risk-in-in-clause)
   - [2.2 API CSRF Token Bypass](#22-api-csrf-token-bypass)
   - [2.3 Auto-Login with Null Password](#23-auto-login-with-null-password)
   - [2.4 File Extension Validation Gaps](#24-file-extension-validation-gaps)
3. [Medium Severity Issues](#medium-severity-issues)
   - [3.1 DOM innerHTML XSS](#31-dom-innerhtml-xss)
   - [3.2 IP Header Spoofing](#32-ip-header-spoofing)
   - [3.3 Plugin Can Add Dangerous MIME Types](#33-plugin-can-add-dangerous-mime-types)
   - [3.4 SQL Quote String Concatenation](#34-sql-quote-string-concatenation)
   - [3.5 Incomplete PHP Extension Blocking](#35-incomplete-php-extension-blocking)
4. [Low Severity Issues](#low-severity-issues)
5. [Security Strengths](#security-strengths)
6. [Implementation Checklist](#implementation-checklist)

---

## Critical Issues

### 1.1 Weak Encryption Key Fallback

**Severity:** 🔴 CRITICAL
**CVSS Score:** 9.1 (Critical)
**CWE:** CWE-321 (Use of Hard-coded Cryptographic Key)

#### Location

```
administrator/components/com_nxpeasyforms/src/Support/Secrets.php
Lines: 98-106
```

#### Vulnerable Code

```php
private static function key(): string
{
    $app = Factory::getApplication();
    $secret = (string) $app->get('secret');

    if ($secret === '') {
        $secret = 'nxp_easy_forms';  // HARDCODED FALLBACK!
    }
    return hash('sha256', $secret, true);
}
```

#### Impact

If the Joomla application secret is not configured or is empty, ALL encrypted data falls back to a known, hardcoded key. This affects:

- Mailchimp API keys
- HubSpot credentials
- Salesforce tokens
- Webhook signing secrets
- CAPTCHA secrets (reCAPTCHA, Turnstile, Friendly Captcha)

An attacker with access to encrypted data can decrypt it using the known fallback key.

#### Fix

Replace the fallback with an exception that prevents operation without proper configuration:

```php
private static function key(): string
{
    $app = Factory::getApplication();
    $secret = (string) $app->get('secret');

    if ($secret === '') {
        throw new \RuntimeException(
            'Joomla application secret must be configured. '
            . 'Please set a secret key in Global Configuration.'
        );
    }

    return hash('sha256', $secret, true);
}
```

#### Alternative Fix (Graceful Degradation)

If you need backwards compatibility, generate a unique key per installation:

```php
private static function key(): string
{
    $app = Factory::getApplication();
    $secret = (string) $app->get('secret');

    if ($secret === '') {
        // Generate and store a unique key for this installation
        $params = ComponentHelper::getParams('com_nxpeasyforms');
        $storedKey = $params->get('encryption_key', '');

        if ($storedKey === '') {
            // Log warning - admin should configure proper secret
            $app->getLogger()->warning(
                'NXP Easy Forms: Using auto-generated encryption key. '
                . 'Configure Joomla secret for better security.'
            );
            // Use a combination of unique server identifiers
            $secret = hash('sha256', __DIR__ . php_uname() . filectime(__FILE__));
        } else {
            $secret = $storedKey;
        }
    }

    return hash('sha256', $secret, true);
}
```

#### Testing

1. Clear Joomla's secret key temporarily
2. Verify the component throws an exception or uses secure fallback
3. Restore the secret key
4. Verify encrypted data is still accessible

---

### 1.2 CSS Injection Vulnerability

**Severity:** 🔴 CRITICAL
**CVSS Score:** 7.5 (High)
**CWE:** CWE-79 (Cross-site Scripting)

#### Location

```
components/com_nxpeasyforms/src/Helper/FormRenderer.php
Lines: 411-426
```

#### Vulnerable Code

```php
private function renderCustomCss(int $formId, ?string $css): string
{
    $css = trim((string) $css);

    if ($css === '') {
        return '';
    }

    try {
        $encoded = json_encode($css, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        return '';
    }

    // VULNERABLE: json_decode returns original string, unescaped
    return sprintf(
        '<style id="nxp-easy-form-style-%d">%s</style>',
        $formId,
        json_decode($encoded, true)
    );
}
```

#### Impact

The `json_encode()` followed by `json_decode()` does NOT sanitize CSS. Malicious CSS can:

1. Inject `</style><script>` tags to execute JavaScript
2. Use CSS expressions (older IE) for script execution
3. Exfiltrate data via CSS selectors and background URLs
4. Perform UI redressing attacks

#### Fix

Implement proper CSS sanitization:

```php
private function renderCustomCss(int $formId, ?string $css): string
{
    $css = trim((string) $css);

    if ($css === '') {
        return '';
    }

    // Remove any attempts to break out of style tag
    $css = str_replace(['</style', '<script', '<?php', '<?='], '', $css);

    // Remove CSS expressions (IE) and javascript: URLs
    $css = preg_replace('/expression\s*\(/i', '', $css);
    $css = preg_replace('/javascript\s*:/i', '', $css);
    $css = preg_replace('/behavior\s*:/i', '', $css);
    $css = preg_replace('/-moz-binding\s*:/i', '', $css);

    // Remove url() with data: or javascript: schemes
    $css = preg_replace(
        '/url\s*\(\s*["\']?\s*(data|javascript):/i',
        'url(blocked:',
        $css
    );

    // Escape HTML entities in the CSS content
    $css = htmlspecialchars($css, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // But we need to unescape CSS-safe characters
    $css = str_replace(
        ['&gt;', '&lt;', '&quot;', '&#039;', '&amp;'],
        ['>', '<', '"', "'", '&'],
        $css
    );

    // Final safety: ensure no closing style tag
    $css = str_ireplace('</style', '&lt;/style', $css);

    return sprintf(
        '<style id="nxp-easy-form-style-%d">%s</style>',
        $formId,
        $css
    );
}
```

#### Alternative Fix (More Restrictive)

Use a CSS sanitizer library or whitelist approach:

```php
private function renderCustomCss(int $formId, ?string $css): string
{
    $css = trim((string) $css);

    if ($css === '') {
        return '';
    }

    // Only allow safe CSS patterns
    // Remove everything that doesn't look like valid CSS
    $safeCss = '';

    // Parse CSS rules - only allow property: value patterns
    if (preg_match_all('/([a-z\-]+)\s*:\s*([^;}{]+);?/i', $css, $matches, PREG_SET_ORDER)) {
        $allowedProperties = [
            'color', 'background', 'background-color', 'font-size', 'font-family',
            'font-weight', 'text-align', 'margin', 'padding', 'border', 'border-radius',
            'width', 'max-width', 'min-width', 'height', 'display', 'flex', 'gap'
            // Add more as needed
        ];

        foreach ($matches as $match) {
            $property = strtolower(trim($match[1]));
            $value = trim($match[2]);

            if (in_array($property, $allowedProperties, true)) {
                // Ensure value doesn't contain dangerous patterns
                if (!preg_match('/url|expression|javascript|behavior/i', $value)) {
                    $safeCss .= $property . ': ' . $value . '; ';
                }
            }
        }
    }

    if ($safeCss === '') {
        return '';
    }

    return sprintf(
        '<style id="nxp-easy-form-style-%d">.nxp-easy-form { %s }</style>',
        $formId,
        $safeCss
    );
}
```

#### Testing

1. Try injecting: `</style><script>alert('XSS')</script><style>`
2. Try injecting: `body{background:url('javascript:alert(1)')}`
3. Try injecting: `*{behavior:url(script.htc)}`
4. Verify all are blocked or sanitized

---

### 1.3 Missing Upload Directory Protection

**Severity:** 🔴 CRITICAL
**CVSS Score:** 8.8 (High)
**CWE:** CWE-434 (Unrestricted Upload of File with Dangerous Type)

#### Location

```
Upload directory: /images/nxpeasyforms/
Missing file: /images/nxpeasyforms/.htaccess
```

#### Impact

Without `.htaccess` protection, if an attacker manages to upload a PHP file (through any bypass), it could be executed by accessing it directly via URL.

#### Fix

Create the following files during component installation:

**File: `/images/nxpeasyforms/.htaccess`**

```apache
# Prevent PHP execution in upload directory
<FilesMatch "\.(php|phtml|phar|php[3-8]|phps|pht|shtml|cgi|pl|py|exe|sh|bat)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# Disable script execution
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

# Prevent directory listing
Options -Indexes

# Force download for unknown types
<IfModule mod_headers.c>
    Header set Content-Disposition "attachment"
    Header set X-Content-Type-Options "nosniff"
</IfModule>
```

**File: `/images/nxpeasyforms/web.config`** (for IIS)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <handlers>
            <remove name="PHP-FastCGI" />
            <remove name="PHP" />
            <remove name="CGI-exe" />
        </handlers>
        <security>
            <requestFiltering>
                <fileExtensions>
                    <add fileExtension=".php" allowed="false" />
                    <add fileExtension=".phtml" allowed="false" />
                    <add fileExtension=".phar" allowed="false" />
                </fileExtensions>
            </requestFiltering>
        </security>
        <staticContent>
            <mimeMap fileExtension=".*" mimeType="application/octet-stream" />
        </staticContent>
    </system.webServer>
</configuration>
```

**File: `/images/nxpeasyforms/index.html`**

```html
<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body></body></html>
```

#### Implementation

Add to the component's installation script or FileUploader:

```php
// In FileUploader.php, add to ensureDirectoryExists() method:
private function ensureDirectoryExists(): void
{
    if (!is_dir($this->basePath)) {
        Folder::create($this->basePath);
    }

    // Create .htaccess if it doesn't exist
    $htaccessPath = $this->basePath . '/.htaccess';
    if (!is_file($htaccessPath)) {
        $htaccessContent = <<<'HTACCESS'
<FilesMatch "\.(php|phtml|phar|php[3-8]|phps|pht|shtml)$">
    Require all denied
</FilesMatch>
Options -Indexes
HTACCESS;
        file_put_contents($htaccessPath, $htaccessContent);
    }

    // Create index.html
    $indexPath = $this->basePath . '/index.html';
    if (!is_file($indexPath)) {
        file_put_contents($indexPath, '<!DOCTYPE html><html><head></head><body></body></html>');
    }
}
```

---

## High Severity Issues

### 2.1 SQL Injection Risk in IN Clause

**Severity:** 🟠 HIGH
**CVSS Score:** 7.5 (High)
**CWE:** CWE-89 (SQL Injection)

#### Location

```
administrator/components/com_nxpeasyforms/src/Service/Repository/SubmissionRepository.php
Lines: 246, 266
```

#### Vulnerable Code

```php
public function findByIds(array $ids): array
{
    // Line 235-240: IDs are filtered to integers
    $ids = array_filter($ids, fn ($id) => is_numeric($id) && (int) $id > 0);
    $ids = array_map('intval', $ids);

    if (empty($ids)) {
        return [];
    }

    // Line 246: VULNERABLE - String concatenation
    $idList = implode(',', $ids);

    $query = $this->db->getQuery(true)
        ->select('...')
        ->from('...')
        // Line 266: Direct injection into query
        ->where($this->db->quoteName('a.id') . ' IN (' . $idList . ')');
}
```

#### Impact

While the IDs are filtered to integers, this pattern:
1. Bypasses the query builder's parameterization
2. Could be exploited through type juggling edge cases
3. Sets a dangerous precedent for copy-paste coding

#### Fix

Use proper parameterized binding:

```php
public function findByIds(array $ids): array
{
    $ids = array_filter($ids, fn ($id) => is_numeric($id) && (int) $id > 0);
    $ids = array_map('intval', $ids);

    if (empty($ids)) {
        return [];
    }

    $query = $this->db->getQuery(true)
        ->select([
            $this->db->quoteName('a.id'),
            // ... other columns
        ])
        ->from($this->db->quoteName('#__nxpeasyforms_submissions', 'a'));

    // Create parameterized placeholders
    $placeholders = [];
    foreach ($ids as $index => $id) {
        $paramName = ':id' . $index;
        $placeholders[] = $paramName;
        $query->bind($paramName, $ids[$index], ParameterType::INTEGER);
    }

    $query->where(
        $this->db->quoteName('a.id') . ' IN (' . implode(',', $placeholders) . ')'
    );

    $this->db->setQuery($query);

    return $this->db->loadAssocList() ?: [];
}
```

#### Alternative Fix (Using whereIn helper if available)

```php
// If using a query builder that supports whereIn:
$query->whereIn($this->db->quoteName('a.id'), $ids);
```

---

### 2.2 API CSRF Token Bypass

**Severity:** 🟠 HIGH
**CVSS Score:** 6.5 (Medium-High)
**CWE:** CWE-352 (Cross-Site Request Forgery)

#### Location

```
api/components/com_nxpeasyforms/src/Controller/SubmissionController.php
Line: 98
```

#### Vulnerable Code

```php
$context = [
    'ip_address' => $this->detectIp(),
    'user_agent' => $this->input->server->getString('HTTP_USER_AGENT', ''),
    'skip_token_validation' => true,  // CSRF bypassed!
];
```

#### Impact

The API endpoint accepts form submissions without CSRF protection, allowing:
1. Cross-site form submission attacks
2. Automated spam submissions from malicious sites
3. Potential data manipulation if forms have side effects

#### Current Mitigations

The component already has:
- Rate limiting (per IP)
- Honeypot fields
- Minimum submission time checking

#### Fix Option 1: Origin Validation

Add origin/referer validation for non-API clients:

```php
public function create(): void
{
    $data = $this->input->json->getArray();

    if (!is_array($data) || empty($data)) {
        $data = $this->input->post->getArray();
    }

    // Validate origin for browser requests
    $origin = $this->input->server->getString('HTTP_ORIGIN', '');
    $referer = $this->input->server->getString('HTTP_REFERER', '');

    $isApiClient = $this->isApiClient();

    if (!$isApiClient && !$this->isValidOrigin($origin, $referer)) {
        $this->respond([
            'success' => false,
            'message' => Text::_('COM_NXPEASYFORMS_ERROR_INVALID_ORIGIN'),
        ], 403, true);
        return;
    }

    $context = [
        'ip_address' => $this->detectIp(),
        'user_agent' => $this->input->server->getString('HTTP_USER_AGENT', ''),
        'skip_token_validation' => $isApiClient,  // Only skip for API clients
    ];

    // ... rest of method
}

private function isApiClient(): bool
{
    $accept = $this->input->server->getString('HTTP_ACCEPT', '');
    $contentType = $this->input->server->getString('CONTENT_TYPE', '');

    // API clients typically send JSON
    return (
        stripos($accept, 'application/json') !== false ||
        stripos($contentType, 'application/json') !== false
    );
}

private function isValidOrigin(string $origin, string $referer): bool
{
    $siteUrl = Uri::root();
    $siteHost = parse_url($siteUrl, PHP_URL_HOST);

    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        return $originHost === $siteHost;
    }

    if ($referer !== '') {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $siteHost;
    }

    // No origin or referer - could be direct API call
    return true;
}
```

#### Fix Option 2: SameSite Cookies

Ensure Joomla's session cookies use SameSite=Strict or SameSite=Lax:

```php
// In Joomla's configuration.php or via plugin
public $cookie_samesite = 'Strict';
```

---

### 2.3 Auto-Login with Null Password

**Severity:** 🟠 HIGH
**CVSS Score:** 5.5 (Medium)
**CWE:** CWE-287 (Improper Authentication)

#### Location

```
administrator/components/com_nxpeasyforms/src/Service/Registration/UserRegistrationHandler.php
Lines: 428-445
```

#### Vulnerable Code

```php
private function autoLogin(User $user): void
{
    $app = Factory::getApplication();

    try {
        $app->login([
            'username' => $user->username,
            'password' => null,  // NULL password!
        ], [
            'remember' => false,
            'silent' => true,    // Silent mode bypasses checks
        ]);
    } catch (\Exception $exception) {
        // Silent failure
    }
}
```

#### Impact

1. Silent login with null password bypasses normal authentication
2. Could allow session fixation attacks
3. No proper session regeneration may occur

#### Fix

Remove auto-login feature or implement properly:

**Option 1: Remove Auto-Login (Recommended)**

```php
// Simply remove the autoLogin call from registerUser():

// Comment out or remove:
// if (!empty($registrationConfig['auto_login']) && $userData['block'] === 0) {
//     $this->autoLogin($user);
// }
```

**Option 2: Use Joomla's Proper Login Flow**

```php
private function autoLogin(User $user, string $password): void
{
    $app = Factory::getApplication();

    try {
        // Use the actual password for proper authentication
        $credentials = [
            'username' => $user->username,
            'password' => $password,
        ];

        $options = [
            'remember' => false,
            'silent' => false,  // Don't use silent mode
        ];

        $result = $app->login($credentials, $options);

        if ($result !== true) {
            $app->getLogger()->warning(
                'NXP Easy Forms: Auto-login failed for user ' . $user->username
            );
        }
    } catch (\Exception $exception) {
        $app->getLogger()->error(
            'NXP Easy Forms: Auto-login exception: ' . $exception->getMessage()
        );
    }
}

// Update the call in registerUser():
if (!empty($registrationConfig['auto_login']) && $userData['block'] === 0) {
    $this->autoLogin($user, $password);  // Pass the plain password
}
```

---

### 2.4 File Extension Validation Gaps

**Severity:** 🟠 HIGH
**CVSS Score:** 7.0 (High)
**CWE:** CWE-434 (Unrestricted Upload of File with Dangerous Type)

#### Location

```
administrator/components/com_nxpeasyforms/src/Service/Validation/FileValidator.php
Lines: 352-371
```

#### Vulnerable Code

```php
private function isExtensionAllowed(string $originalName, string $mime): bool
{
    if ($originalName === '') {
        return true;  // Issue 1: Empty name = allowed
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === '' || strpos($extension, 'php') !== false) {
        return false;
    }

    $allowed = self::MIME_EXTENSION_MAP[$mime] ?? null;

    if ($allowed === null) {
        return true;  // Issue 2: Unknown MIME = any extension allowed
    }

    return in_array($extension, $allowed, true);
}
```

#### Issues

1. **Empty filename returns true** - Files without names are allowed
2. **Unknown MIME type allows any extension** - `.exe`, `.sh`, etc. could pass
3. **Incomplete PHP check** - Misses `.phar`, `.pht`, `.shtml`

#### Fix

```php
private const DANGEROUS_EXTENSIONS = [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8',
    'phtml', 'phar', 'pht', 'phps',
    'exe', 'com', 'bat', 'cmd', 'sh', 'bash', 'zsh',
    'pl', 'py', 'rb', 'cgi',
    'asp', 'aspx', 'jsp', 'jspx',
    'htaccess', 'htpasswd',
    'shtml', 'shtm',
    'svg',  // Can contain JavaScript
];

private function isExtensionAllowed(string $originalName, string $mime): bool
{
    // Reject empty filenames
    if ($originalName === '') {
        return false;
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

    // Reject files without extension
    if ($extension === '') {
        return false;
    }

    // Check against dangerous extensions list
    foreach (self::DANGEROUS_EXTENSIONS as $dangerous) {
        if ($extension === $dangerous || str_contains($extension, $dangerous)) {
            return false;
        }
    }

    // Check double extensions (e.g., file.php.txt)
    $allExtensions = [];
    $filename = $originalName;
    while (($ext = pathinfo($filename, PATHINFO_EXTENSION)) !== '') {
        $allExtensions[] = strtolower($ext);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
    }

    foreach ($allExtensions as $ext) {
        foreach (self::DANGEROUS_EXTENSIONS as $dangerous) {
            if ($ext === $dangerous) {
                return false;
            }
        }
    }

    // MIME type must be in our allowed list
    $allowed = self::MIME_EXTENSION_MAP[$mime] ?? null;

    if ($allowed === null) {
        // Unknown MIME type - reject by default
        return false;
    }

    return in_array($extension, $allowed, true);
}
```

---

## Medium Severity Issues

### 3.1 DOM innerHTML XSS

**Severity:** 🟡 MEDIUM
**CVSS Score:** 5.4 (Medium)
**CWE:** CWE-79 (Cross-site Scripting)

#### Location

```
media/com_nxpeasyforms/src/frontend/country-state-handler.js
Lines: 286, 299, 322, 358
```

#### Vulnerable Code

```javascript
// Line 358 - Most critical
ensureSelectMode(element) {
    if (element.tagName === 'INPUT' && element.dataset.originalSelectHtml) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = element.dataset.originalSelectHtml;  // DANGEROUS!
        const select = wrapper.firstElementChild || wrapper.firstChild;
        element.parentNode.replaceChild(select, element);
        return select;
    }
    return element;
}

// Lines 286, 299, 322
populateCountrySelect(select, countries, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    // ...
}
```

#### Fix

Use DOM APIs instead of innerHTML:

```javascript
ensureSelectMode(element) {
    if (element.tagName === 'INPUT' && element.dataset.originalSelectHtml) {
        // Parse safely using DOMParser
        const parser = new DOMParser();
        const doc = parser.parseFromString(
            element.dataset.originalSelectHtml,
            'text/html'
        );
        const select = doc.body.firstElementChild;

        if (select && select.tagName === 'SELECT') {
            // Clone to avoid XSS from script elements
            const safeSelect = select.cloneNode(true);
            // Remove any script elements that might have been injected
            safeSelect.querySelectorAll('script').forEach(s => s.remove());
            element.parentNode.replaceChild(safeSelect, element);
            return safeSelect;
        }
    }
    return element;
}

populateCountrySelect(select, countries, placeholder) {
    // Clear existing options safely
    while (select.firstChild) {
        select.removeChild(select.firstChild);
    }

    // Add placeholder option safely
    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;  // Safe - uses textContent
    select.appendChild(placeholderOption);

    // Add country options
    countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country.code;
        option.textContent = country.name;  // Safe
        select.appendChild(option);
    });
}
```

---

### 3.2 IP Header Spoofing

**Severity:** 🟡 MEDIUM
**CVSS Score:** 4.3 (Medium)
**CWE:** CWE-290 (Authentication Bypass by Spoofing)

#### Location

```
api/components/com_nxpeasyforms/src/Controller/SubmissionController.php
Lines: 130-151
```

#### Vulnerable Code

```php
private function detectIp(): string
{
    $server = $this->input->server;
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($keys as $key) {
        $value = $server->get($key);
        // ... validates and returns first valid IP
    }
}
```

#### Impact

Attackers can spoof IP addresses to bypass rate limiting by sending fake `X-Forwarded-For` headers.

#### Fix

Only trust proxy headers when behind a known proxy:

```php
private function detectIp(): string
{
    $server = $this->input->server;

    // Get the direct connection IP first
    $remoteAddr = $server->get('REMOTE_ADDR', '');

    // Only trust forwarded headers if behind a trusted proxy
    $trustedProxies = $this->getTrustedProxies();

    if (!empty($trustedProxies) && $this->isIpInList($remoteAddr, $trustedProxies)) {
        // We're behind a trusted proxy, check forwarded headers
        $forwardedHeaders = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];

        foreach ($forwardedHeaders as $header) {
            $value = $server->get($header, '');
            if ($value !== '') {
                $parts = explode(',', $value);
                $candidate = trim($parts[0]);

                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidate;
                }
            }
        }
    }

    // Return direct connection IP
    if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    return '';
}

private function getTrustedProxies(): array
{
    // Get from component configuration or Joomla config
    $params = ComponentHelper::getParams('com_nxpeasyforms');
    $proxies = $params->get('trusted_proxies', '');

    if ($proxies === '') {
        return [];
    }

    return array_map('trim', explode(',', $proxies));
}

private function isIpInList(string $ip, array $list): bool
{
    foreach ($list as $trusted) {
        if ($ip === $trusted) {
            return true;
        }
        // Support CIDR notation
        if (str_contains($trusted, '/') && $this->ipInCidr($ip, $trusted)) {
            return true;
        }
    }
    return false;
}
```

---

### 3.3 Plugin Can Add Dangerous MIME Types

**Severity:** 🟡 MEDIUM
**CVSS Score:** 5.0 (Medium)
**CWE:** CWE-434 (Unrestricted Upload)

#### Location

```
administrator/components/com_nxpeasyforms/src/Service/Validation/FileValidator.php
Lines: 140-144
```

#### Vulnerable Code

```php
$finalAllowedTypes = (array) $this->filterValue(
    'onNxpEasyFormsFilterAllowedFileTypes',
    $allowedTypes,
    ['field' => $field]
);
```

#### Fix

Validate plugin modifications against a blocklist:

```php
private const FORBIDDEN_MIME_TYPES = [
    'application/x-php',
    'application/x-httpd-php',
    'application/x-executable',
    'application/x-msdownload',
    'application/x-msdos-program',
    'text/x-php',
    'text/x-python',
    'text/x-perl',
    'text/x-shellscript',
];

// After getting plugin modifications:
$finalAllowedTypes = (array) $this->filterValue(
    'onNxpEasyFormsFilterAllowedFileTypes',
    $allowedTypes,
    ['field' => $field]
);

// Remove any dangerous MIME types that plugins might have added
$finalAllowedTypes = array_diff($finalAllowedTypes, self::FORBIDDEN_MIME_TYPES);

// Log warning if plugins tried to add dangerous types
$dangerous = array_intersect($originalPluginTypes, self::FORBIDDEN_MIME_TYPES);
if (!empty($dangerous)) {
    Factory::getApplication()->getLogger()->warning(
        'NXP Easy Forms: Plugin attempted to allow dangerous MIME types: '
        . implode(', ', $dangerous)
    );
}
```

---

### 3.4 SQL Quote String Concatenation

**Severity:** 🟡 MEDIUM
**CVSS Score:** 4.5 (Medium)
**CWE:** CWE-89 (SQL Injection)

#### Locations

```
administrator/components/com_nxpeasyforms/src/Model/SubmissionsModel.php
Lines: 99-100, 107, 112

administrator/components/com_nxpeasyforms/src/Model/FormsModel.php
Line: 85
```

#### Vulnerable Pattern

```php
->where($db->quoteName('a.status') . ' = ' . $db->quote($status));
```

#### Fix

Convert to parameterized binding:

```php
// Before:
->where($db->quoteName('a.status') . ' = ' . $db->quote($status));

// After:
->where($db->quoteName('a.status') . ' = :status')
->bind(':status', $status);
```

Full example for SubmissionsModel:

```php
protected function getListQuery()
{
    $db = $this->getDatabase();
    $query = $db->getQuery(true);

    // ... select and from clauses ...

    // Search filter with parameterized binding
    $search = $this->getState('filter.search');
    if (!empty($search)) {
        $searchTerm = '%' . $db->escape($search, true) . '%';
        $query->where(
            '(' .
            $db->quoteName('a.submission_uuid') . ' LIKE :search1' .
            ' OR ' . $db->quoteName('f.title') . ' LIKE :search2' .
            ')'
        )
        ->bind(':search1', $searchTerm)
        ->bind(':search2', $searchTerm);
    }

    // Status filter
    $status = $this->getState('filter.status');
    if (!empty($status)) {
        $query->where($db->quoteName('a.status') . ' = :status')
              ->bind(':status', $status);
    }

    // Form ID filter
    $formId = (int) $this->getState('filter.form_id');
    if ($formId > 0) {
        $query->where($db->quoteName('a.form_id') . ' = :formId')
              ->bind(':formId', $formId, ParameterType::INTEGER);
    }

    return $query;
}
```

---

### 3.5 Incomplete PHP Extension Blocking

**Severity:** 🟡 MEDIUM
**CVSS Score:** 5.5 (Medium)
**CWE:** CWE-434 (Unrestricted Upload)

#### Location

```
administrator/components/com_nxpeasyforms/src/Service/Validation/FileValidator.php
Line: 360
```

#### Current Code

```php
if ($extension === '' || strpos($extension, 'php') !== false) {
    return false;
}
```

#### Issue

This only blocks extensions containing "php" but misses:
- `.phar` (PHP Archive - executable)
- `.pht` (PHP alternate extension on some servers)
- `.shtml` (Server-side includes)
- `.htaccess` (Apache config)

#### Fix

See section 2.4 above for comprehensive fix with DANGEROUS_EXTENSIONS constant.

---

## Low Severity Issues

### 4.1 Information Leakage in Error Messages

Some error messages could reveal internal paths or configuration details. Consider using generic error messages for production.

### 4.2 Missing Content-Security-Policy Headers

Add CSP headers to prevent inline script execution:

```php
// In a system plugin or component dispatcher
$app->setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'");
```

### 4.3 Missing X-Frame-Options

Prevent clickjacking by adding:

```php
$app->setHeader('X-Frame-Options', 'SAMEORIGIN');
```

---

## Security Strengths

The component demonstrates many excellent security practices:

| Feature | Implementation | Notes |
|---------|---------------|-------|
| Input Validation | ✅ Excellent | Uses Joomla Input class with type-safe methods |
| SQL Parameterization | ✅ Good | Most queries use bind() properly |
| Output Escaping | ✅ Good | Uses htmlspecialchars(), Text::_() |
| Rate Limiting | ✅ Implemented | Per-IP throttling with PSR-16 cache |
| Honeypot Fields | ✅ Implemented | Dynamic field naming per form |
| SSRF Prevention | ✅ Strong | Validates webhook URLs, blocks private IPs |
| File Upload Validation | ✅ Multi-layer | MIME detection, size limits, extension mapping |
| Password Hashing | ✅ Native | Uses Joomla's bcrypt implementation |
| Encryption | ✅ Modern | AES-256-CBC with random IV |
| ACL Integration | ✅ Proper | Uses Joomla's authorization system |
| Sensitive Data Handling | ✅ Good | Passwords masked in notifications |

---

## Implementation Checklist

Use this checklist to track security fix implementation:

### Critical (Must Fix Before Production)

- [x] 1.1 Remove hardcoded encryption key fallback ✅ Fixed in Secrets.php:96-106
- [x] 1.2 Fix CSS injection in FormRenderer ✅ Fixed in FormRenderer.php:411-463
- [x] 1.3 Add .htaccess to upload directory ✅ Fixed in FileUploader.php:124-220

### High Priority (Fix in Next Release)

- [x] 2.1 Fix SQL IN clause parameterization ✅ Fixed in SubmissionRepository.php:266-273
- [x] 2.2 Add origin validation for API CSRF ✅ Fixed in SubmissionController.php:96-186
- [x] 2.3 Fix or remove auto-login with null password ✅ Fixed in UserRegistrationHandler.php:205-208, 423-440
- [x] 2.4 Improve file extension validation ✅ Fixed in FileValidator.php:42-77, 406-452

### Medium Priority (Scheduled Maintenance)

- [x] 3.1 Fix DOM innerHTML in JavaScript ✅ Fixed in country-state-handler.js:284-408
- [x] 3.2 Add trusted proxy configuration ✅ Fixed in SubmissionController.php:194-296
- [x] 3.3 Validate plugin MIME type modifications ✅ Fixed in FileValidator.php:187-201
- [x] 3.4 Convert quote() to parameterized binding ✅ Fixed in SubmissionsModel.php:95-118, FormsModel.php:81-86
- [x] 3.5 Add comprehensive dangerous extension list ✅ Fixed in FileValidator.php:49-58

### Low Priority (When Convenient)

- [ ] 4.1 Review error message information leakage
- [ ] 4.2 Add Content-Security-Policy headers
- [ ] 4.3 Add X-Frame-Options header

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | January 2026 | Initial security audit |

---

## Contact

For security-related issues, please contact: security@nexusplugins.com

**Do not disclose security vulnerabilities publicly until they are fixed.**
