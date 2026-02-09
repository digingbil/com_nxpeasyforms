<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Authentication;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseDriver;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles Joomla user login from form submissions.
 *
 * @since 1.0.0
 */
final class UserLoginHandler
{
    private CMSApplicationInterface $app;
    private ?DatabaseDriver $db;

    public function __construct(?CMSApplicationInterface $app = null, ?DatabaseDriver $db = null)
    {
        $this->app = $app ?? Factory::getApplication();
        $resolvedDb = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
        $this->db = $resolvedDb instanceof DatabaseDriver ? $resolvedDb : null;
    }

    /**
     * Attempt to log in a user based on submission data and configuration.
     *
     * Expected config shape:
     * - enabled: bool
     * - identity_mode: 'auto'|'username'|'email'
     * - remember_me: bool
     * - redirect_url: string
     * - field_mapping: { identity: string, password: string, twofactor?: string }
     *
     * @param array<string, mixed> $data    Sanitized submission data
     * @param array<string, mixed> $config  Login integration configuration
     *
    * @return array{success: bool, message: string, user_id: int|null, redirect?: string, reload?: bool}
     */
    public function login(array $data, array $config): array
    {
        $identityField = (string) ($config['field_mapping']['identity'] ?? 'username');
        $passwordField = (string) ($config['field_mapping']['password'] ?? 'password');
        $twoFactorField = (string) ($config['field_mapping']['twofactor'] ?? '');
        $identityMode = (string) ($config['identity_mode'] ?? 'auto');
        $remember = !empty($config['remember_me']);

        $identity = trim((string) ($data[$identityField] ?? ''));
        $password = (string) ($data[$passwordField] ?? '');
        $twoFactor = $twoFactorField !== '' ? (string) ($data[$twoFactorField] ?? '') : '';

        if ($identity === '' || $password === '') {
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_REQUIRED_FIELDS'),
                'user_id' => null,
            ];
        }

        // Resolve username when identity mode is email or auto-detected email
        $username = $identity;

        if ($identityMode === 'email' || ($identityMode === 'auto' && strpos($identity, '@') !== false)) {
            $username = $this->getUsernameByEmail($identity) ?? '';
            if ($username === '') {
                // Fall back to identity as provided; core will fail appropriately
                $username = $identity;
            }
        }

        // Pre-verify credentials against DB when possible to avoid false positives
        $preVerified = false;
        $preUserId = 0;
        $userRow = null;
        if ($this->canQueryDb()) {
            $userRow = $this->loadUserRowByUsername($username);
            if ($userRow === null) {
                return [
                    'success' => false,
                    'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS'),
                    'user_id' => null,
                ];
            }

            // Blocked or pending activation users are treated as invalid credentials (no leakage)
            if ((int)($userRow['block'] ?? 0) === 1 || !empty($userRow['activation'] ?? '')) {
                return [
                    'success' => false,
                    'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS'),
                    'user_id' => null,
                ];
            }

            $hash = (string)($userRow['password'] ?? '');
            $uid = (int)($userRow['id'] ?? 0);
            if ($hash === '' || $uid <= 0 || !UserHelper::verifyPassword($password, $hash, $uid)) {
                return [
                    'success' => false,
                    'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS'),
                    'user_id' => null,
                ];
            }
            $preVerified = true;
            $preUserId = $uid;
        }

        $credentials = [
            'username' => $username,
            'password' => $password,
        ];

        // Support optional 2FA secret field if provided
        if ($twoFactor !== '') {
            $credentials['secretkey'] = $twoFactor;
        }

        try {
            $options = [
                'remember' => $remember,
                // 'silent' => true,  // REMOVED: silent mode prevents proper session establishment
            ];

            $result = $this->app->login($credentials, $options);

            // On success, user must be set in the session and have a valid id
            if ($result === true) {
                $user = $this->app->getIdentity();
                $userId = (int) ($user->id ?? 0);
                if ($userId > 0) {
                    $payload = [
                        'userId' => $userId,
                        'username' => (string) $user->username,
                    ];
                    // Fire event to allow listeners to react
                    $this->app->triggerEvent('onNxpEasyFormsUserLoggedIn', [$payload]);

                    $response = [
                        'success' => true,
                        'message' => Text::_('COM_NXPEASYFORMS_MESSAGE_LOGIN_SUCCESS'),
                        'user_id' => $userId,
                    ];

                    $redirect = trim((string) ($config['redirect_url'] ?? ''));
                    if ($redirect !== '') {
                        $response['redirect'] = $redirect;
                    } else {
                        $response['reload'] = true;
                    }

                    return $response;
                }
                // If userId is 0 after successful login, something is wrong
                // Fall through to check pre-verified credentials (API context)
            }

            // If session identity was not established in this app but credentials were pre-verified,
            // consider authentication successful (typical when called from API app without shared session).
            if ($preVerified && $preUserId > 0) {
                // If user has 2FA enabled and no code provided, do not report success
                if ($twoFactor === '' && $this->userHasTwoFactor($preUserId)) {
                    return [
                        'success' => false,
                        'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS'),
                        'user_id' => null,
                    ];
                }

                $response = [
                    'success' => true,
                    'message' => Text::_('COM_NXPEASYFORMS_MESSAGE_LOGIN_SUCCESS'),
                    'user_id' => $preUserId,
                ];
                $redirect = trim((string) ($config['redirect_url'] ?? ''));
                if ($redirect !== '') {
                    $response['redirect'] = $redirect;
                } else {
                    $response['reload'] = true;
                }
                return $response;
            }

            // Treat as failure otherwise
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS'),
                'user_id' => null,
            ];
        } catch (\Throwable $e) {
            // Avoid exposing sensitive details
            return [
                'success' => false,
                'message' => Text::_('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS'),
                'user_id' => null,
            ];
        }
    }

    private function canQueryDb(): bool
    {
    return $this->db instanceof DatabaseDriver && \method_exists($this->db, 'getQuery');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadUserRowByUsername(string $username): ?array
    {
        if (!$this->db instanceof DatabaseDriver) {
            return null;
        }

        try {
            $db = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id','username','email','password','block','activation']))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('username') . ' = :username')
                ->bind(':username', $username);
            $db->setQuery($query);
            $row = $db->loadAssoc();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lookup username by email.
     *
     * @param string $email
     * @return string|null Username if found
     */
    private function getUsernameByEmail(string $email): ?string
    {
        if (!$this->db instanceof DatabaseDriver) {
            return null;
        }

        try {
            $db = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('username'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('email') . ' = :email')
                ->bind(':email', $email);
            $db->setQuery($query);
            $username = (string) $db->loadResult();
            return $username !== '' ? $username : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if user has any 2FA method enabled in user profiles.
     */
    private function userHasTwoFactor(int $userId): bool
    {
        if (!$this->db instanceof DatabaseDriver) {
            return false;
        }

        try {
            $db = $this->db;
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__user_profiles'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('%twofactor%'))
                ->bind(':uid', $userId, \PDO::PARAM_INT);
            $db->setQuery($query);
            $count = (int) $db->loadResult();
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
