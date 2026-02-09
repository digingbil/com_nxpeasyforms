<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Helper\FormDefaults;
use Joomla\Component\Nxpeasyforms\Administrator\Service\SubmissionService;


use function array_map;
use function array_replace_recursive;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function trim;
use function uniqid;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class FormRenderer
{
    private InputFilter $filter;

    /**
     * @var array<int, bool>
     */
    private static array $stylesRendered = [];

    public function __construct(?InputFilter $filter = null)
    {
        $this->filter = $filter ?? InputFilter::getInstance();

        // Load frontend language file for user-facing messages
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_nxpeasyforms', JPATH_SITE);
    }

    /**
     * @param array<string, mixed> $form
     */
    public function render(array $form): string
    {
        $config = $this->mergeConfig($form['config'] ?? []);
        $fields = $config['fields'];
        $options = $config['options'];

        if (!$this->containsButton($fields)) {
            $fields[] = [
                'type' => 'button',
                'label' => Text::_('JSUBMIT'),
            ];
        }

        $formId = (int) ($form['id'] ?? 0);
        $successMessage = $this->escapeAttr($options['success_message'] ?? Text::_('COM_NXPEASYFORMS_MESSAGE_SUBMISSION_SUCCESS'));
        $errorMessage = $this->escapeAttr($options['error_message'] ?? Text::_('COM_NXPEASYFORMS_ERROR_VALIDATION'));

        $messageContainer = '<div class="nxp-easy-form__messages" aria-live="polite"></div>';
        $fieldsMarkup = $this->renderFields($fields);
        $captchaMarkup = $this->renderCaptcha($options['captcha'] ?? []);
        $hiddenInputs = $this->renderHiddenFields($formId);
        $captchaProvider = $this->escapeAttr((string) ($options['captcha']['provider'] ?? 'none'));

        // Extract provider-specific site key
        $captchaSiteKey = '';
        if ($captchaProvider !== 'none' && isset($options['captcha'][$captchaProvider]['site_key'])) {
            $captchaSiteKey = $this->escapeAttr((string) $options['captcha'][$captchaProvider]['site_key']);
        }

        $captchaAttributes = sprintf(
            ' data-captcha-provider="%s"%s',
            $captchaProvider,
            $captchaSiteKey !== '' ? sprintf(' data-captcha-site-key="%s"', $captchaSiteKey) : ''
        );

        $enctype = $this->containsFileField($fields) ? ' enctype="multipart/form-data"' : '';

        // Check if this is a login form (user_login integration enabled)
        $isLoginForm = !empty($options['integrations']['user_login']['enabled']);
        $loginFormAttr = $isLoginForm ? ' data-is-login-form="1"' : '';

        $customCss = $this->renderCustomCss($formId, $options['custom_css'] ?? '');

        if ($isLoginForm && $this->isUserLoggedIn()) {
            return $this->renderLoggedInState($formId, $customCss);
        }

        $formTag = sprintf(
            '<form class="nxp-easy-form__form" method="post" novalidate role="form" data-success-message="%s" data-error-message="%s"%s>%s%s%s%s</form>',
            $successMessage,
            $errorMessage,
            $enctype,
            $messageContainer,
            $fieldsMarkup,
            $captchaMarkup,
            $hiddenInputs
        );

        return sprintf(
            '<div class="nxp-easy-form" data-form-id="%d"%s%s>%s%s<noscript>%s</noscript></div>',
            $formId,
            $captchaAttributes,
            $loginFormAttr,
            $formTag,
            $customCss,
            $this->escape(Text::_('COM_NXPEASYFORMS_FORM_REQUIRES_JAVASCRIPT'))
        );
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function renderFields(array $fields): string
    {
        $output = [];

        foreach ($fields as $field) {
            $type = is_string($field['type'] ?? null) ? $field['type'] : 'text';

            switch ($type) {
                case 'button':
                    $output[] = $this->renderButton($field);
                    break;
                case 'checkbox':
                    $output[] = $this->renderCheckbox($field);
                    break;
                case 'textarea':
                    $output[] = $this->renderTextarea($field);
                    break;
                case 'custom_text':
                    $output[] = $this->renderCustomText($field);
                    break;
                case 'select':
                    $output[] = $this->renderSelect($field);
                    break;
                case 'radio':
                    $output[] = $this->renderRadio($field);
                    break;
                case 'file':
                    $output[] = $this->renderFile($field);
                    break;
                case 'date':
                    $output[] = $this->renderDate($field);
                    break;
                case 'hidden':
                    $output[] = $this->renderHidden($field);
                    break;
                case 'country':
                    $output[] = $this->renderCountry($field);
                    break;
                case 'state':
                    $output[] = $this->renderState($field);
                    break;
                default:
                    $output[] = $this->renderInput($field);
                    break;
            }
        }

        return implode('', $output);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderInput(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $placeholder = $this->escapeAttr($field['placeholder'] ?? '');
        $required = !empty($field['required']);

        $type = 'text';
        if (in_array($field['type'], ['email', 'tel', 'password'], true)) {
            $type = $field['type'];
        }

        return sprintf(
            '<div class="nxp-easy-form__group"><label class="nxp-easy-form__label" for="%1$s">%2$s%5$s</label><input class="nxp-easy-form__input" type="%6$s" id="%1$s" name="%3$s" placeholder="%4$s" %7$s /><p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p></div>',
            $id,
            $label,
            $name,
            $placeholder,
            $required ? '<span class="nxp-easy-form__required">*</span>' : '',
            $this->escapeAttr($type),
            $required ? 'required' : ''
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderTextarea(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $placeholder = $this->escapeAttr($field['placeholder'] ?? '');
        $required = !empty($field['required']);

        return sprintf(
            '<div class="nxp-easy-form__group"><label class="nxp-easy-form__label" for="%1$s">%2$s%4$s</label><textarea class="nxp-easy-form__textarea" id="%1$s" name="%3$s" placeholder="%5$s" %6$s></textarea><p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p></div>',
            $id,
            $label,
            $name,
            $required ? '<span class="nxp-easy-form__required">*</span>' : '',
            $placeholder,
            $required ? 'required' : ''
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderCheckbox(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');

        return sprintf(
            '<div class="nxp-easy-form__group nxp-easy-form__group--checkbox"><label class="nxp-easy-form__checkbox"><input type="checkbox" id="%1$s" name="%2$s" value="1" /> <span>%3$s</span></label><p class="nxp-easy-form__error" data-error-for="%2$s" role="alert"></p></div>',
            $id,
            $name,
            $label
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderSelect(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $multiple = !empty($field['multiple']);

        $optionMarkup = implode('', array_map(function ($option) {
            $value = $this->escapeAttr(is_string($option) ? $option : ($option['value'] ?? ''));
            $text = $this->escape(is_string($option) ? $option : ($option['label'] ?? $value));

            return sprintf('<option value="%s">%s</option>', $value, $text);
        }, $options));

        $nameAttribute = $multiple ? $name . '[]' : $name;
        $multipleAttr = $multiple ? ' multiple' : '';

        return sprintf(
            '<div class="nxp-easy-form__group"><label class="nxp-easy-form__label" for="%1$s">%2$s</label><select class="nxp-easy-form__select" id="%1$s" name="%3$s"%4$s>%5$s</select><p class="nxp-easy-form__error" data-error-for="%6$s" role="alert"></p></div>',
            $id,
            $label,
            $this->escapeAttr($nameAttribute),
            $multipleAttr,
            $optionMarkup,
            $this->escapeAttr($name)
        );
    }

    /**
     * Render a country select field.
     * The options are populated client-side via JavaScript from the REST API.
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @return string Rendered HTML.
     * @since 1.0.6
     */
    private function renderCountry(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $placeholder = $this->escape($field['placeholder'] ?? Text::_('COM_NXPEASYFORMS_SELECT_COUNTRY'));
        $required = !empty($field['required']);
        $countryFilter = $this->escapeAttr($field['country_filter'] ?? 'all');

        return sprintf(
            '<div class="nxp-easy-form__group">'
            . '<label class="nxp-easy-form__label" for="%1$s">%2$s%5$s</label>'
            . '<select class="nxp-easy-form__select nxp-easy-form__country" id="%1$s" name="%3$s" data-country-filter="%6$s"%7$s>'
            . '<option value="">%4$s</option>'
            . '</select>'
            . '<p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p>'
            . '</div>',
            $id,
            $label,
            $name,
            $placeholder,
            $required ? '<span class="nxp-easy-form__required">*</span>' : '',
            $countryFilter,
            $required ? ' required' : ''
        );
    }

    /**
     * Render a state/region select field.
     * The options are populated client-side via JavaScript based on selected country.
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @return string Rendered HTML.
     * @since 1.0.6
     */
    private function renderState(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $placeholder = $this->escape($field['placeholder'] ?? Text::_('COM_NXPEASYFORMS_SELECT_STATE'));
        $required = !empty($field['required']);
        $countryField = $this->escapeAttr($field['country_field'] ?? '');
        $allowText = !empty($field['allow_text_input']) ? '1' : '0';

        return sprintf(
            '<div class="nxp-easy-form__group">'
            . '<label class="nxp-easy-form__label" for="%1$s">%2$s%5$s</label>'
            . '<select class="nxp-easy-form__select nxp-easy-form__state" id="%1$s" name="%3$s" data-country-field="%6$s" data-allow-text="%7$s"%8$s>'
            . '<option value="">%4$s</option>'
            . '</select>'
            . '<p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p>'
            . '</div>',
            $id,
            $label,
            $name,
            $placeholder,
            $required ? '<span class="nxp-easy-form__required">*</span>' : '',
            $countryField,
            $allowText,
            $required ? ' required' : ''
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderRadio(array $field): string
    {
        $name = $this->escapeAttr($field['name'] ?? uniqid('nxp', true));
        $label = $this->escape($field['label'] ?? '');
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];

        $choices = [];
        foreach ($options as $index => $option) {
            $value = $this->escapeAttr(is_string($option) ? $option : ($option['value'] ?? ''));
            $text = $this->escape(is_string($option) ? $option : ($option['label'] ?? $value));
            $choiceId = $name . '-' . $index;
            $choices[] = sprintf('<label class="nxp-easy-form__radio"><input type="radio" name="%1$s" id="%2$s" value="%3$s" /> <span>%4$s</span></label>', $name, $choiceId, $value, $text);
        }

        return sprintf(
            '<div class="nxp-easy-form__group"><span class="nxp-easy-form__label">%1$s</span><div class="nxp-easy-form__choices">%2$s</div><p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p></div>',
            $label,
            implode('', $choices),
            $name
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderFile(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $required = !empty($field['required']);

        return sprintf(
            '<div class="nxp-easy-form__group"><label class="nxp-easy-form__label" for="%1$s">%2$s%4$s</label><input class="nxp-easy-form__input" type="file" id="%1$s" name="%3$s" %5$s /><p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p></div>',
            $id,
            $label,
            $name,
            $required ? '<span class="nxp-easy-form__required">*</span>' : '',
            $required ? 'required' : ''
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderDate(array $field): string
    {
        $id = $this->escapeAttr($field['id'] ?? $field['name'] ?? uniqid('nxp', true));
        $name = $this->escapeAttr($field['name'] ?? $id);
        $label = $this->escape($field['label'] ?? '');
        $required = !empty($field['required']);

        return sprintf(
            '<div class="nxp-easy-form__group"><label class="nxp-easy-form__label" for="%1$s">%2$s%4$s</label><input class="nxp-easy-form__input" type="date" id="%1$s" name="%3$s" %5$s /><p class="nxp-easy-form__error" data-error-for="%3$s" role="alert"></p></div>',
            $id,
            $label,
            $name,
            $required ? '<span class="nxp-easy-form__required">*</span>' : '',
            $required ? 'required' : ''
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderHidden(array $field): string
    {
        $name = $this->escapeAttr($field['name'] ?? uniqid('nxp', true));
        $value = $this->escapeAttr($field['value'] ?? '');

        return sprintf('<input type="hidden" name="%s" value="%s" />', $name, $value);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderButton(array $field): string
    {
        $label = $this->escape($field['label'] ?? Text::_('JSUBMIT'));

        return sprintf('<div class="nxp-easy-form__actions"><button type="submit" class="nxp-easy-form__button">%s</button></div>', $label);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderCustomText(array $field): string
    {
        $content = is_string($field['content'] ?? null) ? $field['content'] : '';
        $sanitised = $this->filter->clean($content, 'HTML');

        return sprintf('<div class="nxp-easy-form__custom">%s</div>', $sanitised);
    }

    private function renderCaptcha(array $config): string
    {
        $provider = is_string($config['provider'] ?? null) ? $config['provider'] : 'none';

        // Extract provider-specific site key
        $siteKey = '';
        if ($provider !== 'none' && isset($config[$provider]['site_key'])) {
            $siteKey = is_string($config[$provider]['site_key']) ? $config[$provider]['site_key'] : '';
        }

        $hidden = '<input type="hidden" name="_nxp_captcha_token" value="" />';

        if ($provider === 'turnstile' && $siteKey !== '') {
            return sprintf('<div class="cf-turnstile" data-sitekey="%s" data-theme="auto"></div>%s', $this->escapeAttr($siteKey), $hidden);
        }

        if ($provider === 'friendlycaptcha' && $siteKey !== '') {
            return sprintf('<div class="frc-captcha" data-sitekey="%s"></div>%s', $this->escapeAttr($siteKey), $hidden);
        }

        return $hidden;
    }

    private function renderHiddenFields(int $formId): string
    {
        try {
            $tokenField = Factory::getApplication()->getFormToken();
        } catch (\Throwable $exception) {
            $tokenField = '';
        }

        $tokenInput = $tokenField !== ''
            ? sprintf('<input type="hidden" name="%s" value="1" />', $this->escapeAttr($tokenField))
            : '';

        $formIdInput = sprintf('<input type="hidden" name="formId" value="%d" />', $formId);

        $honeypotName = SubmissionService::honeypotFieldName($formId);
        $timestampName = SubmissionService::timestampFieldName($formId);

        $honeypot = sprintf(
            '<div class="nxp-easy-form__nxp_f" aria-hidden="true"><label><input type="text" name="%1$s" tabindex="-1" autocomplete="off" /></label></div><input type="hidden" name="%2$s" value="%3$d" />',
            $this->escapeAttr($honeypotName),
            $this->escapeAttr($timestampName),
            time()
        );

        return $tokenInput . $formIdInput . $honeypot;
    }

    private function renderCustomCss(int $formId, ?string $css): string
    {
        $css = trim((string) $css);

        if ($css === '') {
            return '';
        }

        // Sanitize CSS to prevent injection attacks
        $css = $this->sanitizeCss($css);

        if ($css === '') {
            return '';
        }

        return sprintf('<style id="nxp-easy-form-style-%d">%s</style>', $formId, $css);
    }

    /**
     * Sanitize CSS content to prevent XSS and injection attacks.
     *
     * @param string $css Raw CSS content.
     *
     * @return string Sanitized CSS content.
     * @since 1.0.6
     */
    private function sanitizeCss(string $css): string
    {
        // Remove any attempts to break out of style tag
        $css = str_ireplace(['</style', '<style', '<script', '<?php', '<?=', '<%'], '', $css);

        // Remove CSS expressions (IE) and javascript: URLs
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/vbscript\s*:/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        $css = preg_replace('/-moz-binding\s*:/i', '', $css);
        $css = preg_replace('/-webkit-binding\s*:/i', '', $css);

        // Remove url() with data: or javascript: schemes
        $css = preg_replace(
            '/url\s*\(\s*["\']?\s*(data|javascript|vbscript):/i',
            'url(blocked:',
            $css
        );

        // Remove @import statements that could load external malicious CSS
        $css = preg_replace('/@import\s+/i', '', $css);

        // Remove @charset which could be used for encoding attacks
        $css = preg_replace('/@charset\s+/i', '', $css);

        return $css;
    }

    private function renderLoggedInState(int $formId, string $customCss): string
    {
        $userName = $this->getLoggedInUserName();
        $messageRaw = $userName !== ''
            ? Text::sprintf('COM_NXPEASYFORMS_MESSAGE_ALREADY_LOGGED_IN_AS', $userName)
            : Text::_('COM_NXPEASYFORMS_MESSAGE_ALREADY_LOGGED_IN');
        $message = $this->escape($messageRaw);

        $logoutAction = $this->escapeAttr('index.php?option=com_users&task=user.logout');

        try {
            $token = Factory::getApplication()->getFormToken();
        } catch (\Throwable $exception) {
            $token = '';
        }

        $tokenName = $token !== '' ? $this->escapeAttr($token) : '';
        $tokenInput = $tokenName !== ''
            ? sprintf('<input type="hidden" name="%s" value="1" />', $tokenName)
            : '';

        $returnRaw = base64_encode('index.php');
        $returnAttr = $this->escapeAttr($returnRaw);
        $buttonLabel = $this->escape(Text::_('COM_NXPEASYFORMS_BUTTON_LOGOUT'));
        $noscript = $this->escape(Text::_('COM_NXPEASYFORMS_FORM_REQUIRES_JAVASCRIPT'));

        $messageContainer = sprintf(
            '<div class="nxp-easy-form__messages" aria-live="polite"><div class="nxp-easy-form__notice nxp-easy-form__notice--info">%s</div></div>',
            $message
        );

        $logoutForm = sprintf(
            '<form class="nxp-easy-form__logout" action="%s" method="post">'
            . '%s'
            . '<input type="hidden" name="return" value="%s" />'
            . '<button type="submit" class="nxp-easy-form__button nxp-easy-form__button--logout">%s</button>'
            . '</form>',
            $logoutAction,
            $tokenInput,
            $returnAttr,
            $buttonLabel
        );

        return sprintf(
            '<div class="nxp-easy-form nxp-easy-form--logged-in" data-form-id="%d" data-is-login-form="1">%s%s%s<noscript>%s</noscript></div>',
            $formId,
            $messageContainer,
            $logoutForm,
            $customCss,
            $noscript
        );
    }

    private function isUserLoggedIn(): bool
    {
        try {
            $app = Factory::getApplication();
            $user = $app->getIdentity();

            return (int) ($user->id ?? 0) > 0;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function getLoggedInUserName(): string
    {
        try {
            $app = Factory::getApplication();
            $user = $app->getIdentity();

            if (!empty($user->name)) {
                return (string) $user->name;
            }

            if (!empty($user->username)) {
                return (string) $user->username;
            }
        } catch (\Throwable $exception) {
            return '';
        }

        return '';
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function containsFileField(array $fields): bool
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'file') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function containsButton(array $fields): bool
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'button') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function mergeConfig(array $config): array
    {
        $defaults = FormDefaults::builderConfig();

        return [
            'fields' => is_array($config['fields'] ?? null) ? $config['fields'] : $defaults['fields'],
            'options' => array_replace_recursive($defaults['options'], is_array($config['options'] ?? null) ? $config['options'] : []),
        ];
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    private function escapeAttr(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
