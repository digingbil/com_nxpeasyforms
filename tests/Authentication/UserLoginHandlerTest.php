<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Authentication;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Authentication\UserLoginHandler;
use Joomla\CMS\Application\CMSApplicationInterface;
use PHPUnit\Framework\TestCase;

final class UserLoginHandlerTest extends TestCase
{
    private function makeApp(bool $loginResult, int $userId = 0, string $username = ''): CMSApplicationInterface
    {
        return new class($loginResult, $userId, $username) implements CMSApplicationInterface {
            private bool $result;
            private ?object $identity = null;

            public function __construct(bool $result, int $id, string $username)
            {
                $this->result = $result;
                if ($result) {
                    $this->identity = (object) ['id' => $id, 'username' => $username];
                }
            }

            public function login(array $credentials, array $options = [])
            {
                return $this->result;
            }

            public function triggerEvent(string $name, array $args = []): void
            {
                // no-op for tests
            }

            public function getIdentity()
            {
                return $this->identity;
            }
        };
    }

    public function testLoginSuccessWithUsernameMode(): void
    {
        $app = $this->makeApp(true, 42, 'demo');
        $handler = new UserLoginHandler($app, null);

        $data = [
            'user' => 'demo',
            'pass' => 'secret',
        ];

        $config = [
            'identity_mode' => 'username',
            'remember_me' => true,
            'redirect_url' => '/members',
            'field_mapping' => [
                'identity' => 'user',
                'password' => 'pass',
            ],
        ];

        $result = $handler->login($data, $config);

        $this->assertTrue($result['success']);
        $this->assertSame('COM_NXPEASYFORMS_MESSAGE_LOGIN_SUCCESS', $result['message']);
        $this->assertSame(42, $result['user_id']);
        $this->assertSame('/members', $result['redirect']);
    }

    public function testLoginSuccessWithoutRedirectTriggersReload(): void
    {
        $app = $this->makeApp(true, 99, 'reload-user');
        $handler = new UserLoginHandler($app, null);

        $data = [
            'user' => 'reload-user',
            'pass' => 'secret',
        ];

        $config = [
            'identity_mode' => 'username',
            'remember_me' => false,
            'field_mapping' => [
                'identity' => 'user',
                'password' => 'pass',
            ],
        ];

        $result = $handler->login($data, $config);

        $this->assertTrue($result['success']);
        $this->assertSame('COM_NXPEASYFORMS_MESSAGE_LOGIN_SUCCESS', $result['message']);
        $this->assertSame(99, $result['user_id']);
        $this->assertTrue($result['reload']);
        $this->assertArrayNotHasKey('redirect', $result);
    }

    public function testLoginFailureWithInvalidCredentials(): void
    {
        $app = $this->makeApp(false);
        $handler = new UserLoginHandler($app, null);

        $data = [
            'user' => 'demo',
            'pass' => 'wrong',
        ];

        $config = [
            'identity_mode' => 'username',
            'remember_me' => false,
            'field_mapping' => [
                'identity' => 'user',
                'password' => 'pass',
            ],
        ];

        $result = $handler->login($data, $config);

        $this->assertFalse($result['success']);
        $this->assertSame('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS', $result['message']);
        $this->assertNull($result['user_id']);
    }
}
