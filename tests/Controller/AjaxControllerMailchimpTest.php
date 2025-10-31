<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Controller;

use Joomla\Component\Nxpeasyforms\Administrator\Controller\AjaxController;
use Joomla\Component\Nxpeasyforms\Administrator\Support\Secrets;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AjaxControllerMailchimpTest extends TestCase
{
    private object $controller;

    private ReflectionMethod $normalizeMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new ReflectionClass(AjaxController::class);
        $this->controller = $reflection->newInstanceWithoutConstructor();
        $this->normalizeMethod = $reflection->getMethod('normalizeMailchimpIntegration');
        $this->normalizeMethod->setAccessible(true);
    }

    public function testEncryptsNewApiKey(): void
    {
        $settings = [
            'enabled' => true,
            'api_key' => 'abcd1234-us1',
            'tags' => ['Tag One', '', null],
        ];

        /** @var array<string,mixed> $normalized */
        $normalized = $this->normalizeMethod->invoke($this->controller, $settings, []);

        $this->assertTrue($normalized['api_key_set']);
        $this->assertNotSame($settings['api_key'], $normalized['api_key']);
        $this->assertSame($settings['api_key'], Secrets::decrypt($normalized['api_key']));
        $this->assertSame(['Tag One'], $normalized['tags']);
        $this->assertFalse($normalized['remove_api_key']);
    }

    public function testRemovingApiKeyClearsStoredSecret(): void
    {
        $existing = [
            'api_key' => Secrets::encrypt('abcd1234-us1'),
            'api_key_set' => true,
        ];

        $settings = [
            'remove_api_key' => true,
        ];

        /** @var array<string,mixed> $normalized */
        $normalized = $this->normalizeMethod->invoke($this->controller, $settings, $existing);

        $this->assertSame('', $normalized['api_key']);
        $this->assertFalse($normalized['api_key_set']);
    }

    public function testExistingEncryptedKeyIsPreserved(): void
    {
        $encrypted = Secrets::encrypt('legacykey-us2');

        $existing = [
            'api_key' => $encrypted,
            'api_key_set' => true,
        ];

        /** @var array<string,mixed> $normalized */
        $normalized = $this->normalizeMethod->invoke($this->controller, [], $existing);

        $this->assertSame($encrypted, $normalized['api_key']);
        $this->assertTrue($normalized['api_key_set']);
        $this->assertSame('legacykey-us2', Secrets::decrypt($normalized['api_key']));
    }

    public function testLegacyPlainKeyBecomesEncrypted(): void
    {
        $existing = [
            'api_key' => 'plainlegacy-us3',
        ];

        /** @var array<string,mixed> $normalized */
        $normalized = $this->normalizeMethod->invoke($this->controller, [], $existing);

        $this->assertNotSame('plainlegacy-us3', $normalized['api_key']);
        $this->assertTrue($normalized['api_key_set']);
        $this->assertSame('plainlegacy-us3', Secrets::decrypt($normalized['api_key']));
    }
}
