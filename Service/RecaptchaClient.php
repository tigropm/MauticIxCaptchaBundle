<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Service;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticIxCaptchaBundle\Integration\IxCaptchaIntegration;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies reCAPTCHA v3 tokens against the Google siteverify API.
 *
 * Security measures:
 *  - Passes the visitor's IP address to Google (remoteip parameter) to improve
 *    accuracy and reduce replay-attack surface.
 *  - Uses the admin-configurable minimum score stored in the integration settings.
 *  - Never logs or exposes the secret key.
 *  - Uses Symfony HttpClient (PSR-18 compatible) — no direct Guzzle dependency,
 *    compatible with Mautic 5, 6, and 7.
 */
class RecaptchaClient
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private ?string $secretKey = null;
    private float   $minScore;
    private ?IxCaptchaIntegration $integration = null;

    public function __construct(
        private readonly IntegrationHelper $integrationHelper,
        private readonly LoggerInterface   $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack       $requestStack,
    ) {
        /** @var IxCaptchaIntegration|null $integration */
        $integration = $this->integrationHelper->getIntegrationObject(IxCaptchaIntegration::INTEGRATION_NAME);

        if ($integration instanceof IxCaptchaIntegration
            && $integration->getIntegrationSettings()->getIsPublished()
        ) {
            $this->integration = $integration;
            $keys              = $integration->getKeys();
            $this->secretKey   = $keys['secret_key'] ?? null;
            $this->minScore    = $integration->getMinScore();
        } else {
            $this->minScore = IxCaptchaIntegration::DEFAULT_MIN_SCORE;
        }
    }

    /**
     * Verifies a reCAPTCHA v3 token.
     *
     * @param string $token The token submitted by the browser.
     *
     * @return array{success: bool, message?: string, score?: float, errors?: list<string>}
     */
    public function verify(string $token): array
    {
        if (empty($this->secretKey)) {
            $this->logger->error('ixCaptcha: secret key is not configured or integration is unpublished');

            return ['success' => false, 'message' => 'reCAPTCHA is not properly configured'];
        }

        // Sanitise the token — it must be a non-empty alphanumeric string.
        // Google tokens consist of base64url characters + underscores/hyphens.
        if (!preg_match('/^[\w\-]{10,4096}$/', $token)) {
            $this->logger->warning('ixCaptcha: received malformed token');

            return ['success' => false, 'message' => 'Invalid reCAPTCHA token'];
        }

        // Forward the visitor's IP to Google for improved bot-detection accuracy.
        $remoteIp = $this->requestStack->getMainRequest()?->getClientIp() ?? '';

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => array_filter([
                    'secret'   => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]),
                'timeout' => 10,
            ]);

            /** @var array<string, mixed>|null $result */
            $result = $response->toArray(throw: false);

            if (!is_array($result)) {
                $this->logger->error('ixCaptcha: unexpected response format from Google API');

                return ['success' => false, 'message' => 'Unexpected API response'];
            }

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('ixCaptcha: HTTP transport error — ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not reach reCAPTCHA verification service'];
        } catch (\Throwable $e) {
            $this->logger->error('ixCaptcha: unexpected error during verification — ' . $e->getMessage());

            return ['success' => false, 'message' => 'Unexpected error during reCAPTCHA verification'];
        }

        if (empty($result['success'])) {
            $errorCodes = (array) ($result['error-codes'] ?? []);
            $this->logger->warning('ixCaptcha: token rejected by Google', ['error-codes' => $errorCodes]);

            return [
                'success' => false,
                'message' => 'reCAPTCHA verification failed',
                'errors'  => $errorCodes,
            ];
        }

        $score = isset($result['score']) ? (float) $result['score'] : 0.0;

        if ($score < $this->minScore) {
            $this->logger->info('ixCaptcha: score below threshold — possible bot', [
                'score'     => $score,
                'threshold' => $this->minScore,
            ]);

            return [
                'success' => false,
                'message' => 'reCAPTCHA score too low',
                'score'   => $score,
            ];
        }

        $this->logger->info('ixCaptcha: verification successful', ['score' => $score]);

        return ['success' => true, 'score' => $score];
    }
}
