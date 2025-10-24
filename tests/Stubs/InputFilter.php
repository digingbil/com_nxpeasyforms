<?php

declare(strict_types=1);

namespace Joomla\CMS\Filter;

final class InputFilter
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function clean(string $value, string $type): string
    {
        return $value;
    }
}
