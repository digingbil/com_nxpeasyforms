<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

// Provide minimal Joomla Text stub for isolated tests.
require_once __DIR__ . '/Stubs/Text.php';
require_once __DIR__ . '/Stubs/Http.php';
require_once __DIR__ . '/Stubs/Uri.php';
require_once __DIR__ . '/Stubs/Application.php';
require_once __DIR__ . '/Stubs/Factory.php';
require_once __DIR__ . '/Stubs/Mailer.php';
require_once __DIR__ . '/Stubs/Registry.php';
require_once __DIR__ . '/Stubs/InputFilter.php';
require_once __DIR__ . '/Stubs/MVC/Controller/BaseController.php';

if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', sys_get_temp_dir());
}

if (!defined('JPATH_CACHE')) {
    define('JPATH_CACHE', sys_get_temp_dir() . '/cache');
}
