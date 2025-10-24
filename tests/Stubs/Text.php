<?php

declare(strict_types=1);

namespace Joomla\CMS\Language;

final class Text
{
    public static function _(string $string, ...$args): string
    {
        return $args === [] ? $string : vsprintf($string, $args);
    }

    public static function sprintf(string $string, ...$args): string
    {
        return vsprintf($string, $args);
    }
}
