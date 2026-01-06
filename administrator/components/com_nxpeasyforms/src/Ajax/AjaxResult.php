<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Ajax;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Immutable value object representing the outcome of an AJAX handler operation.
 */
final class AjaxResult
{
    /**
     * @var mixed
     */
    private $data;

    /**
     * @var int
     */
    private int $status;

    /**
     * @param mixed $data Payload data to be serialised as JSON.
     * @param int $status HTTP status code representing the outcome.
     */
    public function __construct($data, int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    /**
     * Retrieve the response payload.
     *
     * @return mixed Data returned by the handler.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Retrieve the HTTP status code associated with the result.
     *
     * @return int HTTP status code for the response.
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
