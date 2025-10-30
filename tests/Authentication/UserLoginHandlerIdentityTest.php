<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Authentication;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Authentication\UserLoginHandler;
use Joomla\CMS\Application\CMSApplicationInterface;
use PHPUnit\Framework\TestCase;

final class UserLoginHandlerIdentityTest extends TestCase
{
    private function makeAppLoginTrueButNoIdentity(): CMSApplicationInterface
    {
        return new class() implements CMSApplicationInterface {
            public function login(array $credentials, array $options = []) { return true; }
            public function triggerEvent(string $name, array $args = []): void {}
            public function getIdentity() { return null; }
        };
    }

    public function testLoginTrueButNoIdentityIsFailure(): void
    {
        $app = $this->makeAppLoginTrueButNoIdentity();
        $handler = new UserLoginHandler($app, null);

        $data = [ 'id' => 'whatever', 'pwd' => 'whatever' ];
        $config = [
            'identity_mode' => 'username',
            'field_mapping' => [ 'identity' => 'id', 'password' => 'pwd' ],
        ];

        $result = $handler->login($data, $config);
        $this->assertFalse($result['success']);
        $this->assertSame('COM_NXPEASYFORMS_ERROR_LOGIN_INVALID_CREDENTIALS', $result['message']);
        $this->assertNull($result['user_id']);
    }
}
