<?php
/**
 * @package     NXP Easy Forms
 * @subpackage  com_nxpeasyforms
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Api\Controller;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Response\JsonResponse;

use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function strtoupper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Utility API controller for serving country and state data.
 *
 * @since 1.0.6
 */
final class UtilityController extends ApiController
{
    protected $contentType = 'utility';

    protected $default_view = 'utility';

    /**
     * Return list of countries.
     *
     * @return void
     */
    public function countries(): void
    {
        $mode = $this->input->getString('mode', 'all');
        $countries = $this->loadCountries();

        // Filter by mode if needed (can implement 'popular', 'eu', etc. later)
        if ($mode !== 'all') {
            // For now, return all countries regardless of mode
        }

        $this->respond([
            'success' => true,
            'countries' => $countries,
        ]);
    }

    /**
     * Return list of states/regions for a country.
     *
     * @return void
     */
    public function states(): void
    {
        $countryCode = strtoupper($this->input->getString('country', ''));

        if ($countryCode === '' || strlen($countryCode) !== 2) {
            $this->respond([
                'success' => false,
                'message' => 'Invalid country code',
                'states' => [],
            ], 400);

            return;
        }

        $states = $this->loadStatesForCountry($countryCode);

        $this->respond([
            'success' => true,
            'country' => $countryCode,
            'states' => $states,
        ]);
    }

    /**
     * Load countries data from JSON file.
     *
     * @return array<string, string> Country code => Country name map.
     */
    private function loadCountries(): array
    {
        $path = JPATH_ROOT . '/media/com_nxpeasyforms/assets/data/countries.json';

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Load states/regions for a specific country.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code.
     *
     * @return array<string, string> State code => State name map.
     */
    private function loadStatesForCountry(string $countryCode): array
    {
        $path = JPATH_ROOT . '/media/com_nxpeasyforms/assets/data/states.json';

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $allStates = json_decode($content, true);

        if (!is_array($allStates) || !isset($allStates[$countryCode])) {
            return [];
        }

        return is_array($allStates[$countryCode]) ? $allStates[$countryCode] : [];
    }

    /**
     * Writes a JSON response to the API output buffer.
     *
     * @param array<string, mixed> $payload Response payload.
     * @param int                  $status  HTTP status code.
     *
     * @return void
     */
    private function respond(array $payload, int $status = 200): void
    {
        $response = new JsonResponse($payload, null, $status >= 400);
        $this->app->setHeader('status', (string) $status, true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->sendHeaders();
        echo (string) $response;
        $this->app->close();
    }
}
