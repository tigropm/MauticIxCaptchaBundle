<?php

declare(strict_types=1);

namespace MauticPlugin\MauticIxCaptchaBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

/**
 * reCAPTCHA v3 Integration configuration.
 */
class IxCaptchaIntegration extends AbstractIntegration
{
    public const INTEGRATION_NAME = 'IxCaptcha';

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'ixCaptcha (reCAPTCHA v3)';
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthenticationType(): string
    {
        return 'none';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredKeyFields(): array
    {
        return [
            'site_key'   => 'mautic.ixcaptcha.config.site_key',
            'secret_key' => 'mautic.ixcaptcha.config.secret_key',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Empty implementation - no additional form fields needed beyond the required keys.
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        // No additional fields needed in the simplified version
    }
}
