<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticIxCaptchaBundle\Integration\IxCaptchaIntegration;
use Psr\Log\LoggerInterface;

/**
 * Service for verifying reCAPTCHA tokens with Google API.
 */
class RecaptchaClient
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private ?string $secretKey = null;
    private float $minScore = 0.5;
    private LoggerInterface $logger;

    public function __construct(
        IntegrationHelper $integrationHelper,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->minScore = 0.5; // Fixed score, not configurable

        $integration = $integrationHelper->getIntegrationObject(IxCaptchaIntegration::INTEGRATION_NAME);
        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $keys = $integration->getKeys();
            $this->secretKey = $keys['secret_key'] ?? null;
        }
    }

    /**
     * Verify a reCAPTCHA token with Google API.
     *
     * @param string $token The reCAPTCHA token to verify
     * @param mixed  $field The form field entity
     *
     * @return array Result array with 'success' boolean and optional 'message' and 'score'
     */
    public function verify(string $token, mixed $field): array
    {
        if (empty($this->secretKey)) {
            $this->logger->error('ixCaptcha: Secret key not configured');
            return [
                'success' => false,
                'message' => 'reCAPTCHA is not properly configured',
            ];
        }

        try {
            $client = new Client(['timeout' => 10]);
            $response = $client->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret'   => $this->secretKey,
                    'response' => $token,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!isset($result['success']) || !$result['success']) {
                $errorCodes = $result['error-codes'] ?? [];
                $this->logger->warning('ixCaptcha verification failed', [
                    'errors' => $errorCodes,
                ]);

                return [
                    'success' => false,
                    'message' => 'reCAPTCHA verification failed',
                    'errors'  => $errorCodes,
                ];
            }

            // Score validation (reCAPTCHA v3)
            $score = $result['score'] ?? 0.0;
            if ($score < $this->minScore) {
                $this->logger->info('ixCaptcha score too low', [
                    'score'     => $score,
                    'min_score' => $this->minScore,
                ]);

                return [
                    'success' => false,
                    'message' => 'reCAPTCHA score too low (possible bot)',
                    'score'   => $score,
                ];
            }

            $this->logger->info('ixCaptcha verification successful', [
                'score' => $score,
            ]);

            return [
                'success' => true,
                'score'   => $score,
            ];

        } catch (GuzzleException $e) {
            $this->logger->error('ixCaptcha API error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Could not verify reCAPTCHA',
            ];
        } catch (\Exception $e) {
            $this->logger->error('ixCaptcha unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unexpected error during verification',
            ];
        }
    }
}
