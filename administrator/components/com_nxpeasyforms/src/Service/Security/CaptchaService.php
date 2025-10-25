<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Security;

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Exception\SubmissionException;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;


use const JSON_THROW_ON_ERROR;
use function json_decode;
use function json_encode;
use function is_array;
use function is_string;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Handles CAPTCHA verification across supported providers.
 */
final class CaptchaService
{
    private Http $http;

    private ?DispatcherInterface $dispatcher;

    public function __construct(?Http $http = null, ?DispatcherInterface $dispatcher = null)
    {
        $this->http = $http ?? HttpFactory::getHttp();
        $this->dispatcher = $dispatcher;

        if ($this->dispatcher === null) {
            try {
                $this->dispatcher = \Joomla\CMS\Factory::getApplication()->getDispatcher();
            } catch (\Throwable $exception) {
                $this->dispatcher = null;
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws SubmissionException
     */
    public function verify(string $provider, string $token, array $config): void
    {
        if ($provider === 'none') {
            return;
        }

        if ($token === '') {
            $this->fail();
        }

        $secret = isset($config['secret_key']) ? (string) $config['secret_key'] : '';

        if ($secret === '') {
            $this->fail();
        }

        $ip = isset($config['ip']) ? (string) $config['ip'] : '';
        $formId = isset($config['form_id']) ? (int) $config['form_id'] : 0;

        switch ($provider) {
            case 'recaptcha_v3':
                $this->verifyRecaptchaV3($token, $secret, $ip, $formId);
                break;

            case 'turnstile':
                $this->verifyTurnstile($token, $secret, $ip);
                break;

            case 'friendlycaptcha':
                $siteKey = isset($config['site_key']) ? (string) $config['site_key'] : '';
                if ($siteKey === '') {
                    $this->fail();
                }

                $this->verifyFriendlyCaptcha($token, $secret, $siteKey);
                break;

            default:
                $this->fail();
        }
    }

    /**
     * @throws SubmissionException
     */
    private function verifyRecaptchaV3(string $token, string $secret, string $ipAddress, int $formId): void
    {
        $response = $this->request(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ipAddress,
            ],
            [],
            10
        );

        $body = json_decode($response->body, true);

        if (!is_array($body) || empty($body['success'])) {
            $this->fail();
        }

        $score = isset($body['score']) ? (float) $body['score'] : 0.0;
        $threshold = (float) $this->filterValue(
            'onNxpEasyFormsFilterRecaptchaScore',
            0.5,
            ['formId' => $formId]
        );

        if ($score < $threshold) {
            $this->fail();
        }
    }

    /**
     * @throws SubmissionException
     */
    private function verifyTurnstile(string $token, string $secret, string $ipAddress): void
    {
        $response = $this->request(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ipAddress,
            ],
            [],
            10
        );

        $body = json_decode($response->body, true);

        if (!is_array($body) || empty($body['success'])) {
            $this->fail();
        }
    }

    /**
     * @throws SubmissionException
     */
    private function verifyFriendlyCaptcha(string $token, string $secret, string $siteKey): void
    {
        $response = $this->request(
            'https://api.friendlycaptcha.com/api/v1/siteverify',
            json_encode([
                'solution' => $token,
                'secret' => $secret,
                'sitekey' => $siteKey,
            ], JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json'],
            15
        );

        $body = json_decode($response->body, true);

        if (!is_array($body) || empty($body['success'])) {
            $this->fail();
        }
    }

    /**
     * @throws SubmissionException
     */
    private function fail(): void
    {
        throw new SubmissionException(
            Text::_('COM_NXPEASYFORMS_ERROR_CAPTCHA_FAILED'),
            400
        );
    }

    /**
     * @param array<string, mixed>|string $data
     * @param array<string, string> $headers
     *
     * @throws SubmissionException
     */
    private function request(string $url, $data, array $headers, int $timeout): \Joomla\CMS\Http\Response
    {
        $response = null;

        try {
            $response = $this->http->post($url, $data, $headers, $timeout);
        } catch (\Throwable $exception) {
            $this->fail();
        }

        if ($response === null || (int) $response->code !== 200 || !is_string($response->body)) {
            $this->fail();
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    private function filterValue(string $eventName, $value, array $context = [])
    {
        if ($this->dispatcher === null) {
            return $value;
        }

        $payload = ['value' => &$value] + $context;
        $event = new Event($eventName, $payload);
        $this->dispatcher->dispatch($event->getName(), $event);

        return $event['value'] ?? $value;
    }
}
