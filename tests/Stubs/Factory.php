<?php

declare(strict_types=1);

namespace Joomla\CMS;

use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailerInterface;
use Joomla\CMS\Mail\MailerStub;
use Joomla\Registry\Registry;

final class Factory
{
    private static ?object $application = null;

    private static ?object $container = null;

    public static function getApplication(): object
    {
        if (self::$application instanceof object) {
            return self::$application;
        }

        self::$application = new class() {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'mailfrom' => 'noreply@example.com',
                    'sitename' => 'Test Site',
                    'secret' => 'test-secret',
                    default => $default,
                };
            }

            public function getConfig(): Registry
            {
                return new Registry();
            }

            public function setHeader(string $name, string $value, bool $replace = true): void
            {
                // No-op for tests
            }

            public function getInput(): object
            {
                return new class() {
                    public function getString(string $name, string $default = ''): string
                    {
                        return $default;
                    }

                    public function getMethod(): string
                    {
                        return 'GET';
                    }
                };
            }

            public function close(): void
            {
                // No-op for tests
            }
        };

        return self::$application;
    }

    public static function getMailer(): MailerInterface
    {
        return new MailerStub();
    }

    public static function getContainer(): object
    {
        if (self::$container instanceof object) {
            return self::$container;
        }

        self::$container = new class() {
            public function get(string $id): mixed
            {
                return match ($id) {
                    MailerInterface::class => new MailerStub(),
                    MailerFactoryInterface::class => new class() implements MailerFactoryInterface {
                        public function createMailer(Registry $config): MailerInterface
                        {
                            return new MailerStub();
                        }
                    },
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return in_array(
                    $id,
                    [MailerInterface::class, MailerFactoryInterface::class],
                    true
                );
            }

            public function registerServiceProvider(object $provider): void
            {
                if (method_exists($provider, 'register')) {
                    $provider->register($this);
                }
            }
        };

        return self::$container;
    }
}
