<?php

declare(strict_types=1);

namespace Joomla\CMS;

final class Factory
{
    public static function getApplication(): object
    {
        return new class() {
            public function get(string $key)
            {
                return match ($key) {
                    'mailfrom' => 'noreply@example.com',
                    'sitename' => 'Test Site',
                    default => null,
                };
            }
        };
    }

    public static function getContainer(): object
    {
        return new class() {
            public function get(string $id)
            {
                if ($id === \Joomla\CMS\Mail\MailerInterface::class) {
                    return new \Joomla\CMS\Mail\MailerStub();
                }

                return null;
            }
        };
    }
}
