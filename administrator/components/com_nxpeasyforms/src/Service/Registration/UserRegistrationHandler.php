<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Registration;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Users\Administrator\Model\UserModel;
use Joomla\Database\DatabaseDriver;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles user registration from form submissions.
 *
 * Mirrors WordPress User_Registration_Handler functionality for Joomla.
 *
 * @since 1.0.0
 */
final class UserRegistrationHandler
{
    private DatabaseDriver $db;

    /**
     * Constructor.
     *
     * @param DatabaseDriver|null $db Database driver instance.
     */
    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * Register a new user from form submission data.
     *
     * @param array<string, mixed> $data               Sanitized form data.
     * @param array<string, mixed> $registrationConfig Integration configuration.
     *
     * @return array{success: bool, message: string, user_id: int|null}
     */
    public function registerUser(array $data, array $registrationConfig): array
    {
        $app = Factory::getApplication();
        // Read Users component configuration (authoritative source)
        $usersParams = ComponentHelper::getParams('com_users');
        $usersConfig = (int) $usersParams->get('allowUserRegistration', 0);
        $userActivationMode = (int) $usersParams->get('useractivation', 0); // 0:none, 1:self, 2:admin
        $joomlaDefaultGroup = (int) $usersParams->get('new_usertype', 2);
        $sendPasswordAllowed = (int) $usersParams->get('sendpassword', 1) === 1;

        // Check if user registration is enabled in Joomla
        if ($usersConfig !== 1) {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_DISABLED'),
                'user_id' => null,
            ];
        }

        // Extract field mappings
        $fieldMap = $registrationConfig['field_mapping'] ?? [];
        $username = $this->extractField($data, $fieldMap['username'] ?? 'username');
        $email = $this->extractField($data, $fieldMap['email'] ?? 'email');
        $password = $this->extractField($data, $fieldMap['password'] ?? 'password');
        $name = $this->extractField($data, $fieldMap['name'] ?? 'name');

        // If password mode is 'auto', force generation by clearing any mapped value
        // Password handling: allow mapped password or auto-generate
        $passwordMode = (string) ($registrationConfig['password_mode'] ?? 'auto');
        if ($passwordMode === 'auto') {
            $password = '';
        }

        // Derive username from email if not explicitly mapped
        if ($username === '' && $email !== '') {
            $username = $this->deriveUsernameFromEmail($email);
        }

        // Validate required fields
        if (empty($username) || empty($email)) {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_REQUIRED_FIELDS'),
                'user_id' => null,
            ];
        }

        // Validate username
        if (!$this->isValidUsername($username)) {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_INVALID_USERNAME'),
                'user_id' => null,
            ];
        }

        // Check if username exists
        if ($this->usernameExists($username)) {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_USERNAME_EXISTS'),
                'user_id' => null,
            ];
        }

        // Validate email
        if (!MailHelper::isEmailAddress($email)) {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_INVALID_EMAIL'),
                'user_id' => null,
            ];
        }

        // Check if email exists
        if ($this->emailExists($email)) {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_EMAIL_EXISTS'),
                'user_id' => null,
            ];
        }

        // Generate password if not provided
        $passwordGenerated = false;
        if (empty($password)) {
            $password = UserHelper::genRandomPassword();
            $passwordGenerated = true;
        }

        // If name is not provided, try common fallbacks then username
        if ($name === '') {
            $first = $this->extractField($data, 'first_name');
            $last = $this->extractField($data, 'last_name');
            $full = trim($first . ' ' . $last);
            $name = $full !== '' ? $full : $username;
        }

        // Prepare user data
        $userData = [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'password2' => $password,
            'block' => 0,
            'sendEmail' => 0,
            'registerDate' => Factory::getDate()->toSql(),
        ];

        // Set user group
        // Use form-selected group if provided, otherwise Joomla default
        $userGroup = (int) ($registrationConfig['user_group'] ?? $joomlaDefaultGroup);
        $userData['groups'] = [$userGroup];

        // Determine activation requirements based on Joomla Users config
        // 0 = none, 1 = self, 2 = admin approval
        $requireActivation = $userActivationMode !== 0;
        // Only send an activation link email to the user in self-activation mode
        $sendActivationEmail = $userActivationMode === 1;

        if ($requireActivation) {
            // Block user until activated
            $userData['block'] = 1;
            $userData['activation'] = ApplicationHelper::getHash(UserHelper::genRandomPassword());
        }

        // Create the user using Joomla's User model
        try {
            $user = new User();
            $user->bind($userData);
            $user->save();
            $userId = (int) $user->id;
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage() ?: Text::_('COM_NXPEASYFORMS_ERROR_REGISTRATION_FAILED'),
                'user_id' => null,
            ];
        }

        // Send activation email if required
        if ($requireActivation && $sendActivationEmail) {
            $this->sendActivationEmail($user, $password, $passwordGenerated && $sendPasswordAllowed);
        } elseif (!$requireActivation) {
            // Send welcome email without activation (respect sendpassword config)
            $this->sendWelcomeEmail($user, $password, $passwordGenerated && $sendPasswordAllowed);
        }

        // Notify Super Users when activation flow requires administrator approval
        if ($userActivationMode === 2) {
            $this->sendAdminApprovalEmail($user);
        }

        // Note: Auto-login has been disabled for security reasons.
        // Users should log in through the standard authentication flow.
        // The auto_login configuration option is preserved for backwards compatibility
        // but the feature is intentionally disabled to prevent security bypasses.

        // Dispatch Joomla event
        $app->triggerEvent('onNxpEasyFormsUserRegistered', [$userId, $data]);

        $successMessage = $requireActivation
            ? Text::_('COM_NXPEASYFORMS_MESSAGE_REGISTRATION_ACTIVATION_REQUIRED')
            : Text::_('COM_NXPEASYFORMS_MESSAGE_REGISTRATION_SUCCESS');

        return [
            'success' => true,
            'message' => $successMessage,
            'user_id' => $userId,
        ];
    }

    /**
     * Derive a username from an email address by taking the local part and sanitising it
     * to Joomla's allowed username character set. Ensures a non-empty value.
     *
     * @param string $email
     * @return string
     */
    private function deriveUsernameFromEmail(string $email): string
    {
        $atPos = strpos($email, '@');
        $local = $atPos !== false ? substr($email, 0, $atPos) : $email;

        // Replace disallowed characters with hyphens, collapse repeats, and trim
        $candidate = preg_replace('/[^\p{L}\p{N}_\-\.@]+/u', '-', (string) $local) ?? '';
        $candidate = trim($candidate, '-_.');

        if ($candidate === '') {
            // Fallback to a generic base if local part is unusable
            $candidate = 'user';
        }

        // Ensure uniqueness by appending a counter if needed
        $base = $candidate;
        $suffix = 0;
        while ($this->usernameExists($candidate) || !$this->isValidUsername($candidate)) {
            $suffix++;
            // Limit length conservatively to avoid exceeding db field length (150 in Joomla core)
            $trimmed = mb_substr($base, 0, 140);
            $candidate = $trimmed . '-' . $suffix;
            if ($suffix > 9999) {
                // Give up after many attempts
                break;
            }
        }

        return $candidate;
    }

    /**
     * Extract field value from data using field mapping.
     *
     * @param array<string, mixed> $data      Form submission data.
     * @param string               $fieldName Field name to extract.
     *
     * @return string Field value or empty string.
     */
    private function extractField(array $data, string $fieldName): string
    {
        if (empty($fieldName)) {
            return '';
        }

        $value = $data[$fieldName] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Validate username according to Joomla rules.
     *
     * @param string $username Username to validate.
     *
     * @return bool True if valid.
     */
    private function isValidUsername(string $username): bool
    {
        // Joomla username validation: alphanumeric, underscore, hyphen, period, @ symbol
        return preg_match('/^[\p{L}\p{N}_\-\.@]+$/u', $username) === 1;
    }

    /**
     * Check if username already exists.
     *
     * @param string $username Username to check.
     *
     * @return bool True if exists.
     */
    private function usernameExists(string $username): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('username') . ' = :username')
            ->bind(':username', $username);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * Check if email already exists.
     *
     * @param string $email Email to check.
     *
     * @return bool True if exists.
     */
    private function emailExists(string $email): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('email') . ' = :email')
            ->bind(':email', $email);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * Send activation email to newly registered user.
     *
     * @param User $user              User object.
     * @param string $password        User password.
     * @param bool   $passwordGenerated Whether password was auto-generated.
     *
     * @return void
     */
    private function sendActivationEmail(User $user, string $password, bool $passwordGenerated): void
    {
        $app = Factory::getApplication();
        $config = $app->get('config');
        $sitename = $app->get('sitename');
        $mailfrom = $app->get('mailfrom');
        $fromname = $app->get('fromname');

    $activationUrl = Uri::root() . 'index.php?option=com_users&task=registration.activate&token=' . $user->activation;

        $subject = Text::sprintf('COM_NXPEASYFORMS_EMAIL_REGISTRATION_ACTIVATION_SUBJECT', $sitename);

        $body = Text::sprintf(
            'COM_NXPEASYFORMS_EMAIL_REGISTRATION_ACTIVATION_BODY',
            $user->name,
            $sitename,
            $activationUrl,
            $user->username,
            $passwordGenerated ? $password : '********',
            $sitename
        );

        $mailer = Factory::getMailer();
        $mailer->setSubject($subject);
        $mailer->setBody($body);
        $mailer->addRecipient($user->email);
        $mailer->setSender([$mailfrom, $fromname]);

        try {
            $mailer->Send();
        } catch (\Exception $exception) {
            // Log error but don't fail registration
            $app->enqueueMessage($exception->getMessage(), 'warning');
        }
    }

    /**
     * Send welcome email to newly registered user (no activation required).
     *
     * @param User $user              User object.
     * @param string $password        User password.
     * @param bool   $passwordGenerated Whether password was auto-generated.
     *
     * @return void
     */
    private function sendWelcomeEmail(User $user, string $password, bool $passwordGenerated): void
    {
        $app = Factory::getApplication();
        $sitename = $app->get('sitename');
        $mailfrom = $app->get('mailfrom');
        $fromname = $app->get('fromname');

    $loginUrl = Uri::root() . 'index.php?option=com_users&view=login';

        $subject = Text::sprintf('COM_NXPEASYFORMS_EMAIL_REGISTRATION_WELCOME_SUBJECT', $sitename);

        $body = Text::sprintf(
            'COM_NXPEASYFORMS_EMAIL_REGISTRATION_WELCOME_BODY',
            $user->name,
            $sitename,
            $loginUrl,
            $user->username,
            $passwordGenerated ? $password : '********',
            $sitename
        );

        $mailer = Factory::getMailer();
        $mailer->setSubject($subject);
        $mailer->setBody($body);
        $mailer->addRecipient($user->email);
        $mailer->setSender([$mailfrom, $fromname]);

        try {
            $mailer->Send();
        } catch (\Exception $exception) {
            // Log error but don't fail registration
            $app->enqueueMessage($exception->getMessage(), 'warning');
        }
    }

    /**
     * Auto-login functionality has been disabled for security reasons.
     *
     * This method previously attempted to log in users with a null password,
     * which bypasses proper authentication checks. Users should now log in
     * through the standard Joomla authentication flow after registration.
     *
     * @param User $user User object.
     *
     * @return void
     * @since 1.0.0
     * @deprecated 1.0.6 Auto-login disabled for security. Users must log in manually.
     */
    private function autoLogin(User $user): void
    {
        // Intentionally disabled - do not re-enable without proper authentication
        // Users should log in through the standard authentication flow
        return;
    }

    /**
     * Send an approval-required email to Super Users for admin activation mode.
     *
     * @param User $user Newly created user awaiting approval.
     * @return void
     * @since 1.0.0
     */
    private function sendAdminApprovalEmail(User $user): void
    {
        $app = Factory::getApplication();
        $sitename = $app->get('sitename');
        $mailfrom = $app->get('mailfrom');
        $fromname = $app->get('fromname');

        // Find Super Users dynamically by core.admin permission instead of hardcoded group ID
        $superUserIds = $this->getSuperUserIds();

        if (empty($superUserIds)) {
            return;
        }

        // Fetch recipient emails (only active users who opted to receive system email)
        try {
            $db = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('email'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('block') . ' = 0')
                ->where($db->quoteName('sendEmail') . ' = 1');

            // Use parameterized binding for IN clause
            $placeholders = [];
            foreach ($superUserIds as $index => $id) {
                $paramName = ':uid' . $index;
                $placeholders[] = $paramName;
                $query->bind($paramName, $superUserIds[$index], \Joomla\Database\ParameterType::INTEGER);
            }
            $query->where($db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')');

            $db->setQuery($query);
            $emails = array_column($db->loadAssocList() ?: [], 'email');
        } catch (\Throwable $e) {
            return; // do not fail registration due to admin email lookup
        }

        if (empty($emails)) {
            return;
        }

        $adminUrl = rtrim(Uri::root(), '/') . '/administrator/index.php?option=com_users&task=user.edit&id=' . (int) $user->id;

        $subject = Text::sprintf('COM_NXPEASYFORMS_EMAIL_ADMIN_APPROVAL_SUBJECT', $sitename);
        $body = Text::sprintf(
            'COM_NXPEASYFORMS_EMAIL_ADMIN_APPROVAL_BODY',
            $sitename,
            (string) $user->name,
            (string) $user->username,
            (string) $user->email,
            $adminUrl
        );

        try {
            $mailer = Factory::getMailer();
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->setSender([$mailfrom, $fromname]);
            foreach ($emails as $email) {
                $mailer->addRecipient($email);
            }
            $mailer->Send();
        } catch (\Exception $exception) {
            // Swallow errors; approval email is best-effort
        }
    }

    /**
     * Get user IDs of all Super Users (users with core.admin permission).
     *
     * This method dynamically finds Super Users by checking which groups have
     * the core.admin permission, rather than hardcoding a group ID.
     *
     * @return array<int> Array of user IDs who are Super Users.
     * @since 1.0.6
     */
    private function getSuperUserIds(): array
    {
        try {
            // Get all user groups
            $db = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__usergroups'));

            $db->setQuery($query);
            $groupIds = $db->loadColumn();

            if (empty($groupIds)) {
                return [];
            }

            // Find groups that have core.admin permission
            $superUserGroupIds = [];
            foreach ($groupIds as $groupId) {
                if (Access::checkGroup((int) $groupId, 'core.admin')) {
                    $superUserGroupIds[] = (int) $groupId;
                }
            }

            if (empty($superUserGroupIds)) {
                return [];
            }

            // Get all users in those groups (including child groups)
            $superUserIds = [];
            foreach ($superUserGroupIds as $groupId) {
                $usersInGroup = Access::getUsersByGroup($groupId, true) ?: [];
                $superUserIds = array_merge($superUserIds, array_map('intval', $usersInGroup));
            }

            return array_values(array_unique($superUserIds));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
