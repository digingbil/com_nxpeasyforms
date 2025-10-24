<?php

declare(strict_types=1);

namespace Joomla\Registry;

class Registry
{
    private array $data = [];

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
