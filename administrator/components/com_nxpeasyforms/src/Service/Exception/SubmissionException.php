<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Exception;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Domain exception for submission handling failures.
 * @since 1.0.0
 */
class SubmissionException extends \RuntimeException
{
    private int $status;

    /**
     * @var array<string, mixed>
     * @since 1.0.0
     */
    private array $errors;

    /**
     * @param array<string, mixed> $errors
     *
     * @since 1.0.0
     */
    public function __construct(string $message, int $status = 400, array $errors = [])
    {
        parent::__construct($message, $status);

        $this->status = $status;
        $this->errors = $errors;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     * @since 1.0.0
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
